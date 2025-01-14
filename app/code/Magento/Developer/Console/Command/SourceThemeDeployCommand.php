<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\Developer\Console\Command;

use InvalidArgumentException;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\View\Asset\Publisher;
use Magento\Framework\Console\Cli;
use Magento\Framework\Filesystem\Io\File;
use Magento\Framework\Validator\Locale;
use Magento\Framework\View\Asset\File\NotFoundException;
use Magento\Framework\View\Asset\Repository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class SourceThemeDeployCommand
 *
 * Collects and publishes source files for theme
 */
class SourceThemeDeployCommand extends Command
{
    /**
     * Locale option key
     */
    public const LOCALE_OPTION = 'locale';

    /**
     * Area option key
     */
    public const AREA_OPTION = 'area';

    /**
     * Theme option key
     */
    public const THEME_OPTION = 'theme';

    /**
     * Type argument key
     */
    public const TYPE_ARGUMENT = 'type';

    /**
     * Files argument key
     */
    public const FILE_ARGUMENT = 'file';

    /**
     * @var Locale
     */
    private $validator;

    /**
     * @var Publisher
     */
    private $assetPublisher;

    /**
     * @var Repository
     */
    private $assetRepository;

    /**
     * @var File
     */
    private $file;

    /**
     * Constructor
     *
     * @param Locale $validator
     * @param Publisher $assetPublisher
     * @param Repository $assetRepository
     * @param File|null $file
     */
    public function __construct(
        Locale $validator,
        Publisher $assetPublisher,
        Repository $assetRepository,
        ?File $file = null
    ) {
        parent::__construct('dev:source-theme:deploy');
        $this->validator = $validator;
        $this->assetPublisher = $assetPublisher;
        $this->assetRepository = $assetRepository;
        $this->file = $file ?: ObjectManager::getInstance()->get(File::class);
    }

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        parent::configure();
        $this->setDescription('Collects and publishes source files for theme.')
            ->setDefinition(
                [
                    new InputArgument(
                        self::FILE_ARGUMENT,
                        InputArgument::IS_ARRAY,
                        'Files to pre-process (file should be specified without extension)',
                        ['css/styles-m', 'css/styles-l']
                    ),
                    new InputOption(
                        self::TYPE_ARGUMENT,
                        null,
                        InputOption::VALUE_REQUIRED,
                        'Type of source files: [less]',
                        'less'
                    ),
                    new InputOption(
                        self::LOCALE_OPTION,
                        null,
                        InputOption::VALUE_REQUIRED,
                        'Locale: [en_US]',
                        'en_US'
                    ),
                    new InputOption(
                        self::AREA_OPTION,
                        null,
                        InputOption::VALUE_REQUIRED,
                        'Area: [frontend|adminhtml]',
                        'frontend'
                    ),
                    new InputOption(
                        self::THEME_OPTION,
                        null,
                        InputOption::VALUE_REQUIRED,
                        'Theme: [Vendor/theme]',
                        'Magento/luma'
                    ),

                ]
            );
    }

    /**
     * @inheritdoc
     *
     * @throws InvalidArgumentException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $area = $input->getOption(self::AREA_OPTION);
        $locale = $input->getOption(self::LOCALE_OPTION);
        $theme = $input->getOption(self::THEME_OPTION);
        $type = $input->getOption(self::TYPE_ARGUMENT);

        $files = $input->getArgument(self::FILE_ARGUMENT);

        if (!$this->validator->isValid($locale)) {
            throw new InvalidArgumentException(
                $locale . ' argument has invalid value, please run info:language:list for list of available locales'
            );
        }

        if ($theme === null || !preg_match('#^[\w\-]+\/[\w\-]+$#', $theme)) {
            throw new InvalidArgumentException(
                'Value "' . $theme . '" of the option "' . self::THEME_OPTION .
                '" has invalid format. The format should be "Vendor/theme".'
            );
        }

        $message = sprintf(
            '<info>Processed Area: %s, Locale: %s, Theme: %s, File type: %s.</info>',
            $area,
            $locale,
            $theme,
            $type
        );
        $output->writeln($message);

        foreach ($files as $file) {
            $fileInfo = $this->file->getPathInfo($file);
            $asset = $this->assetRepository->createAsset(
                $fileInfo['dirname'] . DIRECTORY_SEPARATOR . $fileInfo['basename'] . '.' . $type,
                [
                    'area' => $area,
                    'theme' => $theme,
                    'locale' => $locale,
                ]
            );

            try {
                $this->assetPublisher->publish($asset);
            } catch (NotFoundException $e) {
                throw new InvalidArgumentException(
                    'Verify entered values of the argument and options. ' . $e->getMessage()
                );
            }

            $output->writeln('<comment>-> ' . $asset->getFilePath() . '</comment>');
        }

        $output->writeln('<info>Successfully processed.</info>');

        return Cli::RETURN_SUCCESS;
    }
}
