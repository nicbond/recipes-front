<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class SortDirectionIcon extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('sortDirectionIcon', [$this, 'sortDirectionIcon']),
        ];
    }

    /**
     * @param array<string, string> $params
     */
    public function sortDirectionIcon(string $label, array $params): string
    {
        if (array_key_exists('sort', $params) && $label === $params['sort']) {
            if ('asc' === $params['direction']) {
                return 'sort-up';
            }

            return 'sort-down';

        }

        return 'sort';

    }
}
