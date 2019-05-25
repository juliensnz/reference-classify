<?php

declare(strict_types=1);

namespace App\Classifier;

use Akeneo\PimEnterprise\ApiClient\AkeneoPimEnterpriseClientInterface;
use Aws\Credentials\CredentialProvider;
use Aws\Credentials\Credentials;
use Aws\Rekognition\RekognitionClient;

class Rekognition implements Classifier {
    /** @var AkeneoPimEnterpriseClientInterface */
    private $apiClient;

    public function __construct(
        AkeneoPimEnterpriseClientInterface $apiClient,
        string $clientKey,
        string $clientSecret
    ) {

        $this->apiClient = $apiClient;
        $provider = CredentialProvider::fromCredentials(new Credentials(
            $clientKey,
            $clientSecret
        ));
        $memoizedProvider = CredentialProvider::memoize($provider);
        $this->rekognition = new RekognitionClient([
            'region'            => 'eu-west-1',
            'version'           => 'latest',
            'credentials' => $memoizedProvider
        ]);
    }

    public function classify(string $imageCode): array
    {
        $image = $this->fetchFile($imageCode);
        $result = $this->rekognition->detectLabels(array(
            'Image' => array(
                'Bytes' => $image,
            ),
            'Attributes' => array('ALL')
            )
        );

        return $result->get('Labels');
    }

    private function fetchFile(string $imageCode)
    {
        return $this->apiClient->getReferenceEntityMediaFileApi()->download($imageCode)->getBody()->__toString();
    }
}
