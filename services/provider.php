<?php
/**
 * @package    JLSitemap - SW JPojects plugin
 * @version    2.0.0
 * @author     Sergey Tolkachyov - web-tolk.ru
 * @copyright  Copyright (c) 2018-2024 Sergey Tolkachyov. All rights reserved.
 * @license    GNU General Public License v3.0
 * @link       https://web-tolk.ru/dev/joomla-plugins/jlsitemap-swjprojects
 */

\defined('_JEXEC') || die;

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;
use Joomla\Plugin\Jlsitemap\Swjprojects\Extension\Swjprojects;

return new class () implements ServiceProviderInterface {
	/**
	 * Registers the service provider with a DI container.
	 *
	 * @param   Container  $container  The DI container.
	 *
	 * @return  void
	 *
	 * @since   4.0.0
	 */
	public function register(Container $container)
	{
		$container->set(
			PluginInterface::class,
			function (Container $container) {
				$subject = $container->get(DispatcherInterface::class);
				$config  = (array) PluginHelper::getPlugin('jlsitemap', 'swjprojects');
				$plugin = new Swjprojects($subject, $config);
				$plugin->setApplication(Factory::getApplication());
				$plugin->setDatabase(Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class));
				return $plugin;
			}
		);
	}
};
