<?php

namespace YesWiki\Kernel\Command;

use Psr\Container\ContainerInterface;
use Spatie\ImageOptimizer\OptimizerChainFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ImageOptimizerCommand extends Command
{
    protected ContainerInterface $services;

    public function __construct(ContainerInterface $services)
    {
        parent::__construct();
        $this->services = $services;
    }

    protected function configure()
    {
        $this
            ->setName('core:image-optimize')
            ->setDescription('Optimise all images.')
            ->setHelp('Convert all the image files to some decent size and format.')
            ->addOption('forcewebp', 'f', InputOption::VALUE_NONE, 'Convert to webp format');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $optimizerChain = OptimizerChainFactory::create();
        $toWebp = $input->getOption('forcewebp');
        $images = glob('files/*.{jpg,jpeg,png,gif,webp,bmp,svg}', GLOB_BRACE);
        foreach ($images as $image) {
            $beforeSize = $this->humanFilesize(filesize($image));
            echo "Image $image initial size: $beforeSize\n";
            if ($toWebp) {
                $destImage = str_replace('.' . pathinfo($image, PATHINFO_EXTENSION), '.webp', $image);
                // those extensions cannot be converted to webp
                if (in_array(strtolower(pathinfo($image, PATHINFO_EXTENSION)), ['webp', 'gif', 'svg'])) {
                    $destImage = $image;
                    $optimizerChain->optimize($image);
                } else {
                    $optimizerChain->optimize($image, $destImage);
                    unlink($image);
                }
                $afterSize = $this->humanFilesize(filesize($destImage));
            } else {
                $optimizerChain->optimize($image);
                $afterSize = $this->humanFilesize(filesize($image));
            }
            echo "Image size after optimisation: $afterSize\n---\n";
        }

        return Command::SUCCESS;
    }

    public function humanFilesize($bytes, $decimals = 2)
    {
        // `@$sz[$factor - 1]` silenced an undefined variable for anything under a kilobyte,
        // where $sz was never assigned. Indexing the unit list directly says the same thing
        // without the suppression -- and without relying on PHP's negative string offsets,
        // where `'KMGT'[-1]` is 'T' rather than nothing (ticket 40).
        $units = ['', 'K', 'M', 'G', 'T'];
        $factor = (int)min(floor((strlen((string)$bytes) - 1) / 3), count($units) - 1);

        return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . $units[$factor] . 'B';
    }
}
