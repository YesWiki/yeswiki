<?php

namespace YesWiki\Kernel\Command;

use Psr\Container\ContainerInterface;
use Spatie\ImageOptimizer\OptimizerChainFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use YesWiki\Files\Service\Storage;

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
        // The optimiser shells out to jpegoptim, pngquant and friends, so it needs real files.
        // On an instance whose Public tier is a bucket this command has nothing local to hand
        // them, and that is a gap rather than a decision -- `storage:sync` is how those wikis
        // move bytes today.
        $storage = $this->services->get(Storage::class);
        $images = array_filter(
            $storage->files('files'),
            static fn (string $path) => in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'], true)
        );
        foreach ($images as $image) {
            $beforeSize = $this->humanFilesize($storage->fileSize($image));
            echo "Image $image initial size: $beforeSize\n";
            if ($toWebp) {
                $destImage = str_replace('.' . pathinfo($image, PATHINFO_EXTENSION), '.webp', $image);

                if (in_array(strtolower(pathinfo($image, PATHINFO_EXTENSION)), ['webp', 'gif', 'svg'])) {
                    $destImage = $image;
                    $optimizerChain->optimize($image);
                } else {
                    $optimizerChain->optimize($image, $destImage);
                    $storage->delete($image);
                }
                $afterSize = $this->humanFilesize($storage->fileSize($destImage));
            } else {
                $optimizerChain->optimize($image);
                $afterSize = $this->humanFilesize($storage->fileSize($image));
            }
            echo "Image size after optimisation: $afterSize\n---\n";
        }

        return Command::SUCCESS;
    }

    /**
     * @param int|float $bytes
     * @param int       $decimals
     */
    public function humanFilesize($bytes, $decimals = 2): string
    {
        $units = ['', 'K', 'M', 'G', 'T'];
        $factor = (int)min(floor((strlen((string)$bytes) - 1) / 3), count($units) - 1);

        return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . $units[$factor] . 'B';
    }
}
