<?php

declare(strict_types=1);

namespace App\Classifier;

class Cached implements Classifier {
    public function __construct(
        string $filename,
        Rekognition $externalClassifier
    ) {
        $this->filename = $filename;
        $this->externalClassifier = $externalClassifier;
    }

    public function classify(string $imageCode): array
    {
        try {
            $fileContent = file_get_contents($this->filename);
        } catch (\Exception $e) {
            //If file doesn't exists
            $fileContent = '{}';
        }
        $storedResults = json_decode($fileContent, true);

        //If the cache is not warmed up
        if (!isset($storedResults[$imageCode])) {
            $storedResults[$imageCode] = $this->externalClassifier->classify($imageCode);
            file_put_contents($this->filename, json_encode($storedResults));
        }

        return $storedResults[$imageCode];
    }
}
