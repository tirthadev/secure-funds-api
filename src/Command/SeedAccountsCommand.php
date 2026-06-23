<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Account;
use App\Repository\AccountRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:seed-accounts', description: 'Creates two demo USD accounts.')]
final class SeedAccountsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AccountRepository $accounts,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $accounts = [
            new Account('USD', 100_000, '018f7f2e-4f0d-7b31-a932-111111111111'),
            new Account('USD', 25_000, '018f7f2e-4f0d-7b31-a932-222222222222'),
        ];

        foreach ($accounts as $account) {
            if ($this->accounts->find($account->getId()) instanceof Account) {
                $output->writeln(sprintf('%s already exists', $account->getId()));
                continue;
            }

            $this->entityManager->persist($account);
        }

        $this->entityManager->flush();

        foreach ($accounts as $account) {
            $output->writeln(sprintf('%s %s balance=%d', $account->getId(), $account->getCurrency(), $account->getBalanceCents()));
        }

        return Command::SUCCESS;
    }
}
