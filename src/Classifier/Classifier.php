<?php

declare(strict_types=1);

namespace App\Classifier;

interface Classifier {
  public function classify(string $imageCode): array;
}
