<?php
/**
 * @package    JLSitemap - SW JProjects Plugin
 * @version    __DEPLOY_VERSION__
 * @author     Septdir Workshop - www.septdir.com
 * @copyright  Copyright (c) 2018 - 2019 Septdir Workshop. All rights reserved.
 * @license    GNU/GPL license: https://www.gnu.org/copyleft/gpl.html
 * @link       https://www.septdir.com/
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormField;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\FileLayout;
use Joomla\CMS\Toolbar\Toolbar;

class JFormFieldToolbar extends FormField
{
	/**
	 * The form field type.
	 *
	 * @var string
	 *
	 * @since  1.0.0
	 */
	protected $type = 'toolbar';

	/**
	 * Method to add messages and buttons.
	 *
	 * @throws  Exception
	 *
	 * @since   1.0.0
	 */
	protected function getInput()
	{
		$toolbar = Toolbar::getInstance('toolbar');

		// Add support button
		$link = 'https://www.septdir.com/support#solution=jlsitemap-swjprojects';
		$toolbar->appendButton('Custom', $this->getButton($link, 'PLG_JLSITEMAP_SWJPROJECTS_SUPPORT', 'support'),
			'support');

		// Add donate button
		$link = 'https://www.septdir.com/donation#solution=jlsitemap-swjprojects';
		$toolbar->appendButton('Custom', $this->getButton($link, 'PLG_JLSITEMAP_SWJPROJECTS_DONATE', 'heart'),
			'donate');

		// Add donate message
		$message = new FileLayout('donate_message');
		$message->addIncludePath(__DIR__);
		Factory::getApplication()->enqueueMessage($message->render(), '');

		// Toolbar Style
		Factory::getDocument()->addStyleDeclaration('#toolbar-support,#toolbar-donate{float: right;}');
	}

	/**
	 * Method to get toolbar button markup.
	 *
	 * @param   string  $link  Button link.
	 * @param   string  $text  Button text.
	 * @param   string  $icon  Button icon.
	 *
	 * @return  string Buttons markup string.
	 *
	 * @since  1.0.0
	 */
	protected function getButton($link = null, $text = null, $icon = null)
	{
		return '<a href="' . $link . '" class="btn btn-small" target="_blank">'
			. '<span aria-hidden="true" class="icon-' . $icon . '"></span>'
			. Text::_($text) . '</a>';
	}
}