<?php
/**
 * Created by PhpStorm.
 * User: nicolas
 * Date: 07/04/17
 * Time: 10:46
 */

namespace Devgiants\Command;


use Herrera\Phar\Update\Manager;
use Herrera\Phar\Update\Manifest;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateCommand extends Command
{
    const MANIFEST_FILE = 'https://devgiants.github.io/websites-backup/manifest.json';

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this
            ->setName('self-update')
            ->setDescription('Updates website-backup.phar to the latest version')
        ;
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $manager = new Manager(Manifest::loadFile(self::MANIFEST_FILE));
        $manager->update($this->getApplication()->getVersion(), true);
    }
}