<?php
declare(strict_types=1);
namespace Helhum\TYPO3\ConfigHandling\Command;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2017 Helmut Hummel <info@helhum.io>
 *  All rights reserved
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *  A copy is found in the text file GPL.txt and important notices to the license
 *  from the author is found in LICENSE.txt distributed with these scripts.
 *
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use Helhum\TYPO3\ConfigHandling\ConfigCleaner;
use Helhum\TYPO3\ConfigHandling\ConfigDumper;
use Helhum\TYPO3\ConfigHandling\ConfigLoader;
use Helhum\TYPO3\ConfigHandling\RootConfig;
use Helhum\TYPO3\ConfigHandling\Xclass\ConfigurationManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Utility\ArrayUtility;

class SettingsDumpCommand extends Command
{
    /**
     * @var ConfigurationManager
     */
    private $configurationManager;

    /**
     * @var ConfigCleaner
     */
    private $configCleaner;

    /**
     * @var ConfigDumper
     */
    private $configDumper;

    public function __construct(
        $name = null,
        ConfigurationManager $configurationManager = null,
        ConfigCleaner $configCleaner = null,
        ConfigDumper $configDumper = null
    ) {
        parent::__construct($name);
        $this->configurationManager = $configurationManager ?: new ConfigurationManager();
        $this->configCleaner = $configCleaner ?: new ConfigCleaner();
        $this->configDumper = $configDumper ?: new ConfigDumper();
    }

    protected function configure()
    {
        $this->setDefinition(
            [
                new InputOption('--no-dev', null, InputOption::VALUE_NONE, 'When set, only LocalConfiguration.php is written to contain the merged configuration ready for production'),
            ]
        )
        ->setDescription('Dump a (static) LocalConfiguration.php file')
        ->setHelp('The values are complied to respect all settings managed by the configuration loader.');
    }

    /**
     * Dump a (static) LocalConfiguration.php file
     *
     * The values are complied to respect all settings managed by the configuration loader.
     *
     * @throws \RuntimeException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $noDev = $input->getOption('no-dev');
        if ($noDev) {
            $additionalConfigurationFile = $this->configurationManager->getAdditionalConfigurationFileLocation();
            if ($this->isAutoGenerated($additionalConfigurationFile)) {
                unlink($additionalConfigurationFile);
            }
            $configLoader = new ConfigLoader(RootConfig::getRootConfigFile($noDev));
            $config = $this->configCleaner->cleanConfig($configLoader->load());
            $this->configurationManager->writeAdditionalConfiguration(
                [
                    '// Auto generated by helhum/typo3-config-handling',
                    '// Do not edit this file',
                    '$GLOBALS[\'TYPO3_CONF_VARS\'] = ' . ArrayUtility::arrayExport($config) . ';',
                ]
            );
        } else {
            $this->configurationManager->writeAdditionalConfiguration(
                [
                    '// Auto generated by helhum/typo3-config-handling',
                    '// Do not edit this file',
                    RootConfig::getInitConfigFileContent(),
                ]
            );
        }
        $this->configDumper->dumpToFile(
            [],
            $this->configurationManager->getLocalConfigurationFileLocation(),
            "Auto generated by helhum/typo3-config-handling\nDo not edit this file"
        );
    }

    private function isAutoGenerated(string $file): bool
    {
        if (!file_exists($file)) {
            return false;
        }
        return false !== strpos(file_get_contents($file), 'Auto generated by helhum/typo3-config-handling');
    }
}
