<?php

namespace MDOQ\Infrastructure\Console\Command;

use MDOQ\Infrastructure\Api\VarnishServiceInterface;
use MDOQ\Infrastructure\Model\Vcl\SnippetComposer;
use MDOQ\Infrastructure\Model\VarnishManagerClient;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Re-applies module VCL snippets to the VCL currently loaded in Varnish.
 *
 * Run after enabling/disabling a module that registers a snippet or changing its settings
 * (e.g. the Cloudflare tag prefix).
 */
class VclApplyCommand extends Command
{
    private const OPTION_DRY_RUN = 'dry-run';

    public function __construct(
        private VarnishManagerClient $managerClient,
        private VarnishServiceInterface $varnishService,
        private SnippetComposer $snippetComposer
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('mdoq:varnish:vcl:apply')
            ->setDescription('Insert registered module VCL snippets into the live Varnish VCL and upload it')
            ->addOption(self::OPTION_DRY_RUN, null, InputOption::VALUE_NONE, 'Print the resulting VCL without uploading');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $response = $this->managerClient->get('vcl');
        $data = json_decode($response['body'], true);
        $current = is_array($data) ? (string)($data['vcl'] ?? '') : '';
        if ($response['status'] < 200 || $response['status'] >= 300 || trim($current) === '') {
            $output->writeln('<error>Could not read the current VCL from the Varnish manager.</error>');
            return Command::FAILURE;
        }

        $names = $this->snippetComposer->getActiveSnippetNames();
        $composed = $this->snippetComposer->compose($current);

        if ($input->getOption(self::OPTION_DRY_RUN)) {
            $output->writeln($composed, OutputInterface::OUTPUT_RAW);
            return Command::SUCCESS;
        }

        if (rtrim($composed) === rtrim($current)) {
            $output->writeln('<info>Live VCL is already up to date. Snippets: ' . ($names ? implode(', ', $names) : 'none') . '</info>');
            return Command::SUCCESS;
        }

        try {
            $result = $this->varnishService->updateVcl($current);
        } catch (\RuntimeException $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');
            return Command::FAILURE;
        }

        $output->writeln('<info>VCL uploaded. Snippets: ' . ($names ? implode(', ', $names) : 'none') . '</info>');
        if (isset($result['message'])) {
            $output->writeln((string)$result['message']);
        }

        return Command::SUCCESS;
    }
}
