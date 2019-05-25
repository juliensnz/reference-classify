<?php

declare(strict_types=1);

namespace App\Command;

use Akeneo\PimEnterprise\ApiClient\AkeneoPimEnterpriseClientInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use App\Classifier\Cached;

class ClassifyCommand extends Command
{
    protected static $defaultName = 'app:classify';

    private const BATCH_SIZE = 100;

    /** @var SymfonyStyle */
    private $io;

    /** @var AkeneoPimEnterpriseClientInterface */
    private $apiClient;

    public function __construct(
        AkeneoPimEnterpriseClientInterface $apiClient,
        Cached $classifier
    ) {
        parent::__construct(static::$defaultName);

        $this->apiClient = $apiClient;
        $this->classifier = $classifier;
    }

    protected function configure()
    {
        $this
            ->setDescription('Classify a reference entity based on it\'s images')
            ->addArgument('referenceEntityCode', InputArgument::REQUIRED, 'The reference entity code the records belong to.')
            ->addOption('apiUsername', null, InputOption::VALUE_OPTIONAL, 'The username of the user.', getenv('AKENEO_API_USERNAME'))
            ->addOption('apiPassword', null, InputOption::VALUE_OPTIONAL, 'The password of the user.', getenv('AKENEO_API_PASSWORD'))
            ->addOption('apiClientId', null, InputOption::VALUE_OPTIONAL, '', getenv('AKENEO_API_CLIENT_ID'))
            ->addOption('apiClientSecret', null, InputOption::VALUE_OPTIONAL, '', getenv('AKENEO_API_CLIENT_SECRET'))
            ->addOption('tagAttribute', null, InputOption::VALUE_OPTIONAL, '', getenv('TAG_ATTRIBUTE'))
            ->addOption('confidenceThreshold', null, InputOption::VALUE_OPTIONAL, '', 90)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);
        $referenceEntityCode = $input->getArgument('referenceEntityCode');
        $tagAttribute = $input->getOption('tagAttribute');
        $threshold = $input->getOption('confidenceThreshold');
        $imageAttributeCodes = $this->fetchImageAttributeCodes($referenceEntityCode);

        $this->io->title('Record classifier');
        $this->io->text(sprintf('Welcome in the record identifier, we will classify the "%s" Reference entity', $referenceEntityCode));
        $this->io->text(sprintf('We will scan the images in %s and put the result in the "%s" attribute', implode(', ', $imageAttributeCodes), $tagAttribute));
        $this->io->section('Starting the process');

        if (!$this->rawTagExists($referenceEntityCode, $tagAttribute)) {
            $this->io->error(sprintf('The "%s" attribute doesn\'t exists or is not of text type, value per locale or channel', $tagAttribute));

            exit();
        }

        $records = $this->fetchAllRecords($referenceEntityCode);
        $recordsToWrite = [];
        $progressBar = $this->io->createProgressBar();
        $progressBar->start(iterator_count($records));
        $taggedRecordCount = 0;
        foreach ($records as $record) {
            $tags = $this->getTags($record['values'], $imageAttributeCodes, (int) $threshold);
            if (empty($tags)) {
                continue;
            }

            $record['values'] = [
                $tagAttribute => [
                    [
                        'locale' => null,
                        'channel' => null,
                        'data' => implode(',', $tags)
                    ]
                ]
            ];

            $recordsToWrite[] = $record;
            $taggedRecordCount++;

            if (count($recordsToWrite) >= self::BATCH_SIZE) {
                $this->writeRecords($referenceEntityCode, $recordsToWrite);
                $progressBar->advance(count($recordsToWrite));
                $recordsToWrite = [];
            }
        }

        if (count($recordsToWrite) >= 0) {
            $this->writeRecords($referenceEntityCode, $recordsToWrite);
            $progressBar->advance(count($recordsToWrite));
        }

        $progressBar->finish();

        $this->io->newLine();
        $this->io->newLine();
        $this->io->section('Process finished');
        $this->io->text(sprintf('%s record processed', $taggedRecordCount));
    }

    private function getTags(array $values, array $imageAttributeCodes, int $threshold): array
    {
        $tags = [];
        foreach ($imageAttributeCodes as $imageAttributeCode) {
            if (!isset($values[$imageAttributeCode])) {
                continue;
            }

            $rawLabels = $this->classifier->classify($values[$imageAttributeCode][0]['data']);
            foreach($rawLabels as $label) {
                if ($label['Confidence'] > $threshold) {
                    $tags[] = $label['Name'];
                }
            }
        }

        return array_unique($tags);
    }

    private function fetchImageAttributeCodes(string $referenceEntityCode): array
    {
        $attributes = $this->apiClient->getReferenceEntityAttributeApi()->all($referenceEntityCode);

        $imageAttributes = array_filter($attributes, function (array $attribute) {
            return 'image' === $attribute['type'];
        });

        $imageAttributeCodes = array_map(function (array $attribute) {
            return $attribute['code'];
        }, $imageAttributes);

        return $imageAttributeCodes;
    }

    private function rawTagExists(string $referenceEntityCode, string $tagAttribute): bool
    {
        $attributes = $this->apiClient->getReferenceEntityAttributeApi()->all($referenceEntityCode);

        $tagAttribute = array_filter($attributes, function (array $attribute) use ($tagAttribute) {
            return $tagAttribute === $attribute['code'];
        });

        if (empty($tagAttribute)) {
            return false;
        }

        $tagAttribute = current($tagAttribute);

        return 'text' === $tagAttribute['type'] && false === $tagAttribute['value_per_channel'] && false === $tagAttribute['value_per_locale'];
    }

    private function fetchAllRecords(string $referenceEntityCode)
    {
        return $this->apiClient->getReferenceEntityRecordApi()->all($referenceEntityCode);
    }

    private function writeRecords(
      string $referenceEntityCode,
      array $recordsToWrite
    ): void {
        $this->apiClient->getReferenceEntityRecordApi()->upsertList($referenceEntityCode, $recordsToWrite);
    }
}
