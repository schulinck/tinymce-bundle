<?php

namespace Stfalcon\Bundle\TinymceBundle\Composer;

use Composer\Script\Event;
use Composer\Composer;

/**
 * Class InstallationHandler.
 *
 * @package Stfalcon\Bundle\TinymceBundle\Composer
 */
class InstallationHandler
{
    const TINYMCE_PACKAGE_NAME = 'tinymce/tinymce';

    /**
     * @param \Composer\Script\Event $event
     */
    public static function createSymlink(Event $event)
    {
        $localPath = sprintf('%s/../Resources/public/vendor/tinymce', __DIR__);
        if (is_link($localPath)) {
            return;
        }

        $tinymceFilePath = self::getPackageInstallationPath($event->getComposer(), self::TINYMCE_PACKAGE_NAME);
        if (!symlink($tinymceFilePath, $localPath)) {
            throw new \RuntimeException("Unable to create the symlink '$localPath' to the folder '$tinymceFilePath'");
        }
    }

    /**
     * @param \Composer\Composer $composer
     * @param string $packageName
     * @return string
     *
     * @throws \RuntimeException
     */
    protected static function getPackageInstallationPath(Composer $composer, $packageName)
    {
        /* @var \Composer\Repository\PackageRepository $repoManager */
        $repoManager = $composer->getRepositoryManager();

        /* @var \Composer\Installer\InstallationManager $installManager */
        $installManager = $composer->getInstallationManager();

        $package = $repoManager->findPackage($packageName, '*');
        if (empty($package)) {
            throw new \RuntimeException("Unable to find the '$packageName' package.");
        }

        return $installManager->getInstallPath($package);
    }
}
