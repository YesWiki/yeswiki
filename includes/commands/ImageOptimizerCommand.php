<?php

namespace YesWiki\Core\Commands;

use Spatie\ImageOptimizer\OptimizerChainFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use YesWiki\Wiki;

class ImageOptimizerCommand extends Command
{
    protected static $defaultName = 'core:image-optimize';
    protected $wiki;

    public function __construct(Wiki &$wiki)
    {
        parent::__construct();
        $this->wiki = $wiki;
    }

    protected function configure()
    {
        $this
            ->setDescription('Optimise all images.')
            ->setHelp('Convert all the image files to some decent size and format.')
            ->addOption('forcewebp', 'f', InputOption::VALUE_NONE, 'Convert to webp format');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
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
        $factor = floor((strlen($bytes) - 1) / 3);
        if ($factor > 0) {
            $sz = 'KMGT';
        }

        return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . @$sz[$factor - 1] . 'B';
    }
}
