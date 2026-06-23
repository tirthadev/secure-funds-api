<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\TransferRequest;
use App\Service\TransferService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/transfers')]
final readonly class TransferController
{
    public function __construct(
        private TransferService $transferService,
        private ValidatorInterface $validator,
    ) {
    }

    #[Route('', name: 'api_transfers_create', methods: ['POST'])]
    public function create(Request $httpRequest): JsonResponse
    {
        $payload = json_decode($httpRequest->getContent(), true);
        if (!is_array($payload)) {
            return $this->validationError(['body' => 'Request body must be valid JSON.']);
        }

        $request = TransferRequest::fromPayload($payload, $httpRequest->headers->get('Idempotency-Key'));
        $violations = $this->validator->validate($request);
        if ($violations->count() > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[$violation->getPropertyPath()][] = $violation->getMessage();
            }

            return $this->validationError($errors);
        }

        $transfer = $this->transferService->transfer($request);

        return new JsonResponse(['transfer' => $transfer], 201);
    }

    private function validationError(array $errors): JsonResponse
    {
        return new JsonResponse([
            'error' => [
                'code' => 'validation_failed',
                'message' => 'The request payload is invalid.',
                'details' => $errors,
            ],
        ], 422);
    }
}
