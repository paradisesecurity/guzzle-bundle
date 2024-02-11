<?php

declare(strict_types=1);

namespace ParadiseSecurity\Bundle\GuzzleBundle\Twig\Extension;

use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\HtmlDumper;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class DebugExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction(
                'paradise_security_guzzle_dump',
                [$this, 'dump'],
                ['is_safe' => ['html'], 'needs_environment' => true]
            ),
        ];
    }

    public function dump(Environment $env, $value): bool|string
    {
        $cloner = new VarCloner();

        $dump = fopen('php://memory', 'r+b');
        $dumper = new HtmlDumper($dump, $env->getCharset());

        $dumper->dump($cloner->cloneVar($value));
        rewind($dump);

        return stream_get_contents($dump);
    }
}
