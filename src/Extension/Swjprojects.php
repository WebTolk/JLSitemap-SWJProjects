<?php
/**
 * @package    JLSitemap - SW JPojects plugin
 * @version    2.0.0
 * @author     Sergey Tolkachyov - web-tolk.ru
 * @copyright  Copyright (c) 2018-2024 Sergey Tolkachyov. All rights reserved.
 * @license    GNU General Public License v3.0
 * @link       https://web-tolk.ru/dev/joomla-plugins/jlsitemap-swjprojects
 */

namespace Joomla\Plugin\Jlsitemap\Swjprojects\Extension;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Language\LanguageHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Component\SWJProjects\Site\Helper\RouteHelper;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

final class Swjprojects extends CMSPlugin implements SubscriberInterface
{
	use DatabaseAwareTrait;

	/**
	 * Affects constructor behavior.
	 *
	 * @var boolean $autoloadLanguage
	 *
	 * @since  1.0.0
	 */
	protected $autoloadLanguage = true;

	/**
	 * Translates languages.
	 *
	 * @var  array $translates
	 *
	 * @since  1.0.0
	 */
	protected $translates = null;

	/**
	 * Returns an array of events this subscriber will listen to.
	 *
	 * @return  array
	 *
	 * @since   4.0.0
	 */
	public static function getSubscribedEvents(): array
	{
		return [
			'onGetUrls' => 'onGetUrls',
		];
	}


	/**
	 * Method to get urls array.
	 *
	 *
	 * @return  array  Urls array with attributes.
	 *
	 * @since  1.0.0
	 */
	public function onGetUrls(Event $event)
	{
		/**
		 * @var   array    $urls   Urls array.
		 * @var   Registry $config Component config.
		 */
		[$urls, $config] = $event->getArguments();
		// Set translates
		$this->translates = [
			'current' => $this->getApplication()->getLanguage()->getTag(),
			'default' => ComponentHelper::getParams('com_languages')->get('site', 'en-GB'),
			'all'     => \array_keys(LanguageHelper::getLanguages('lang_code'))
		];

		// Exclude judate & download views
		$jupdate             = new \stdClass();
		$jupdate->type       = Text::_('PLG_JLSITEMAP_SWJPROJECTS_TYPES_JUPDATE');
		$jupdate->title      = Text::_('PLG_JLSITEMAP_SWJPROJECTS_TYPES_JUPDATE');
		$jupdate->loc        = 'index.php?option=com_swjprojects&view=jupdate&key=1';
		$jupdate->changefreq = 0;
		$jupdate->priority   = 0;
		$jupdate->exclude    = [
			[
				'type' => Text::_('PLG_JLSITEMAP_SWJPROJECTS_EXCLUDE_JUPDATE'),
				'msg'  => Text::_('PLG_JLSITEMAP_SWJPROJECTS_EXCLUDE_JUPDATE_MSG'),
			]
		];

		$download             = new \stdClass();
		$download->type       = Text::_('PLG_JLSITEMAP_SWJPROJECTS_TYPES_DOWNLOAD');
		$download->title      = Text::_('PLG_JLSITEMAP_SWJPROJECTS_TYPES_DOWNLOAD');
		$download->loc        = 'index.php?option=com_swjprojects&view=download&key=1';
		$download->changefreq = 0;
		$download->priority   = 0;
		$download->exclude    = [
			[
				'type' => Text::_('PLG_JLSITEMAP_SWJPROJECTS_EXCLUDE_DOWNLOAD'),
				'msg'  => Text::_('PLG_JLSITEMAP_SWJPROJECTS_EXCLUDE_DOWNLOAD_MSG'),
			]
		];

		if ($config->get('multilanguage'))
		{
			foreach ($this->translates['all'] as $translate)
			{
				$url      = clone $jupdate;
				$url->loc .= '&lang=' . $translate;
				$urls[]   = $url;

				$url      = clone $download;
				$url->loc .= '&lang=' . $translate;
				$urls[]   = $url;
			}
		}
		else
		{
			$urls[] = $jupdate;
			$urls[] = $download;
		}

		// Add urls
		if (!$this->params->get('projects_enable')
			&& !$this->params->get('project_enable')
			&& !$this->params->get('versions_enable')
			&& !$this->params->get('version_enable'))
		{
			return $urls;
		}

		$db            = $this->getDatabase();
		$multilanguage = $config->get('multilanguage');
		$current       = $this->translates['current'];
		$default       = $this->translates['default'];
		$all           = $this->translates['all'];
		foreach ($all as $key => $code)
		{
			$all[$key] = $db->quote($code);
		}


		// Add projects categories to sitemap
		if ($this->params->get('projects_enable'))
		{
			$query = $db->getQuery(true)
				->select(['c.id', 'c.alias', 'c.state'])
				->from($db->quoteName('#__swjprojects_categories', 'c'))
				->where($db->quoteName('c.alias') . '!=' . $db->quote('root'))
				->group(['c.id', 't_c.language']);

			// Join over translates
			$query->select(['t_c.title', 't_c.language', 't_c.metadata']);
			if ($multilanguage)
			{
				$query->leftJoin($db->quoteName('#__swjprojects_translate_categories', 't_c')
					. '  ON t_c.id = c.id AND ' . $db->quoteName('t_c.language') . 'IN (' . implode(',', $all) . ')');
			}
			else
			{
				$query->leftJoin($db->quoteName('#__swjprojects_translate_categories', 't_c')
					. '  ON t_c.id = c.id AND ' . $db->quoteName('t_c.language') . ' = ' . $db->quote($current));
			}

			// Join over default translates
			$query->select(['td_c.title as default_title'])
				->leftJoin($db->quoteName('#__swjprojects_translate_categories', 'td_c')
					. ' ON td_c.id = c.id AND ' . $db->quoteName('td_c.language') . ' = ' . $db->quote($default));

			$rows       = $db->setQuery($query)->loadObjectList();
			$changefreq = $this->params->get('projects_changefreq', $config->get('changefreq', 'weekly'));
			$priority   = $this->params->get('projects_priority', $config->get('priority', '0.5'));

			foreach ($rows as $row)
			{
				// Prepare title attribute
				$title = (!empty($row->title)) ? $row->title : $row->default_title;
				if (empty($title))
				{
					$title = $row->alias;
				}

				// Prepare loc attribute
				$slug = $row->id . ':' . $row->alias;
				$loc  = RouteHelper::getProjectsRoute($slug);
				if ($multilanguage)
				{
					$loc .= '&lang=' . $row->language;
				}

				// Prepare exclude attribute
				$metadata   = new Registry($row->metadata);
				$exclude    = [];
				$siteRobots = $metadata->get('robots', $config->get('siteRobots'));
				if (!empty($siteRobots) && \preg_match('/noindex/', $siteRobots))
				{
					$exclude[] = ['type' => Text::_('PLG_JLSITEMAP_SWJPROJECTS_EXCLUDE_CATEGORY'),
					              'msg'  => Text::_('PLG_JLSITEMAP_SWJPROJECTS_EXCLUDE_CATEGORY_ROBOTS')];
				}
				if ($row->state != 1)
				{
					$exclude[] = [
						'type' => Text::_('PLG_JLSITEMAP_SWJPROJECTS_EXCLUDE_CATEGORY'),
						'msg'  => ($row->state == -1)
							? Text::_('PLG_JLSITEMAP_SWJPROJECTS_EXCLUDE_CATEGORY_TRASH')
							: Text::_('PLG_JLSITEMAP_SWJPROJECTS_EXCLUDE_CATEGORY_UNPUBLISH')
					];
				}

				// Prepare category object
				$category             = new \stdClass();
				$category->type       = Text::_('PLG_JLSITEMAP_SWJPROJECTS_TYPES_PROJECTS');
				$category->title      = $title;
				$category->loc        = $loc;
				$category->changefreq = $changefreq;
				$category->priority   = $priority;
				$category->exclude    = (!empty($exclude)) ? $exclude : false;
				$category->alternates = ($multilanguage) ? [] : false;

				if ($category->alternates !== false)
				{
					foreach ($this->translates['all'] as $code)
					{
						$category->alternates[$code] = RouteHelper::getProjectsRoute($slug) . '&lang=' . $code;
					}
				}

				// Add category to array
				$urls[] = $category;
			}
		}

		// Add projects to sitemap
		if ($this->params->get('project_enable') || $this->params->get('versions_enable'))
		{
			$query = $db->getQuery(true)
				->select(['p.id', 'p.alias', 'p.state'])
				->from($db->quoteName('#__swjprojects_projects', 'p'))
				->where($db->quoteName('p.visible'). ' = '. $db->quote('1'))
				->group(['p.id', 't_p.language']);

			// Join over categories
			$query->select(['c.id as category_id', 'c.alias as category_alias', 'c.state as category_state'])
				->leftJoin($db->quoteName('#__swjprojects_categories', 'c') . ' ON c.id = p.catid');

			// Join over translates
			$query->select(['t_p.title', 't_p.language', 't_p.metadata']);
			if ($multilanguage)
			{
				$query->leftJoin($db->quoteName('#__swjprojects_translate_projects', 't_p')
					. '  ON t_p.id = p.id AND ' . $db->quoteName('t_p.language') . 'IN (' . implode(',', $all) . ')');
			}
			else
			{
				$query->leftJoin($db->quoteName('#__swjprojects_translate_projects', 't_p')
					. '  ON t_p.id = p.id AND ' . $db->quoteName('t_p.language') . ' = ' . $db->quote($current));
			}

			// Join over default translates
			$query->select(['td_p.title as default_title'])
				->leftJoin($db->quoteName('#__swjprojects_translate_projects', 'td_p')
					. ' ON td_p.id = p.id AND ' . $db->quoteName('td_p.language') . ' = ' . $db->quote($default));

			$rows               = $db->setQuery($query)->loadObjectList();
			$changefreq         = $this->params->get('project_changefreq', $config->get('changefreq', 'weekly'));
			$priority           = $this->params->get('project_priority', $config->get('priority', '0.5'));
			$versionsChangefreq = $this->params->get('versions_changefreq', $config->get('changefreq', 'weekly'));
			$versionsPriority   = $this->params->get('versions_priority', $config->get('priority', '0.5'));

			foreach ($rows as $row)
			{
				// Prepare title attribute
				$title = (!empty($row->title)) ? $row->title : $row->default_title;
				if (empty($title))
				{
					$title = $row->alias;
				}

				// Prepare loc attribute
				$slug        = $row->id . ':' . $row->alias;
				$catslug     = $row->category_id . ':' . $row->category_alias;
				$loc         = RouteHelper::getProjectRoute($slug, $catslug);
				$versionsLoc = RouteHelper::getVersionsRoute($slug, $catslug);
				if ($multilanguage)
				{
					$loc         .= '&lang=' . $row->language;
					$versionsLoc .= '&lang=' . $row->language;
				}

				// Prepare exclude attribute
				$metadata        = new Registry($row->metadata);
				$projectExclude  = [];
				$versionsExclude = [];
				$siteRobots      = $metadata->get('robots', $config->get('siteRobots'));
				if (!empty($siteRobots) && \preg_match('/noindex/', $siteRobots))
				{
					$projectExclude[] = ['type' => Text::_('PLG_JLSITEMAP_SWJPROJECTS_EXCLUDE_PROJECT'),
					                     'msg'  => Text::_('PLG_JLSITEMAP_SWJPROJECTS_EXCLUDE_PROJECT_ROBOTS')];
				}
				if (!empty($siteRobots) && \preg_match('/noindex/', $siteRobots))
				{
					$versionsExclude[] = ['type' => Text::_('PLG_JLSITEMAP_SWJPROJECTS_EXCLUDE_VERSIONS'),
					                      'msg'  => Text::_('PLG_JLSITEMAP_SWJPROJECTS_EXCLUDE_VERSIONS_ROBOTS')];
				}
				if ($row->state != 1)
				{
					$projectExclude[] = [
						'type' => Text::_('PLG_JLSITEMAP_SWJPROJECTS_EXCLUDE_PROJECT'),
						'msg'  => ($row->state == -1)
							? Text::_('PLG_JLSITEMAP_SWJPROJECTS_EXCLUDE_PROJECT_TRASH')
							: Text::_('PLG_JLSITEMAP_SWJPROJECTS_EXCLUDE_PROJECT_UNPUBLISH')
					];

					$versionsExclude[] = [
						'type' => Text::_('PLG_JLSITEMAP_SWJPROJECTS_EXCLUDE_PROJECT'),
						'msg'  => ($row->state == -1)
							? Text::_('PLG_JLSITEMAP_SWJPROJECTS_EXCLUDE_PROJECT_TRASH')
							: Text::_('PLG_JLSITEMAP_SWJPROJECTS_EXCLUDE_PROJECT_UNPUBLISH')
					];


				}
				if ($row->category_state != 1)
				{
					$projectExclude[] = [
						'type' => Text::_('PLG_JLSITEMAP_SWJPROJECTS_EXCLUDE_CATEGORY'),
						'msg'  => ($row->state == -1)
							? Text::_('PLG_JLSITEMAP_SWJPROJECTS_EXCLUDE_CATEGORY_TRASH')
							: Text::_('PLG_JLSITEMAP_SWJPROJECTS_EXCLUDE_CATEGORY_UNPUBLISH')
					];

					$versionsExclude[] = [
						'type' => Text::_('PLG_JLSITEMAP_SWJPROJECTS_EXCLUDE_CATEGORY'),
						'msg'  => ($row->state == -1)
							? Text::_('PLG_JLSITEMAP_SWJPROJECTS_EXCLUDE_CATEGORY_TRASH')
							: Text::_('PLG_JLSITEMAP_SWJPROJECTS_EXCLUDE_CATEGORY_UNPUBLISH')
					];
				}

				// Prepare project object
				$project             = new \stdClass();
				$project->type       = Text::_('PLG_JLSITEMAP_SWJPROJECTS_TYPES_PROJECT');
				$project->title      = $title;
				$project->loc        = $loc;
				$project->changefreq = $changefreq;
				$project->priority   = $priority;
				$project->exclude    = (!empty($projectExclude)) ? $projectExclude : false;
				$project->alternates = ($multilanguage) ? [] : false;

				// Prepare versions object
				$versions             = new \stdClass();
				$versions->type       = Text::_('PLG_JLSITEMAP_SWJPROJECTS_TYPES_VERSIONS');
				$versions->title      = Text::sprintf('PLG_JLSITEMAP_SWJPROJECTS_TYPES_VERSIONS_TITLE', $title);
				$versions->loc        = $versionsLoc;
				$versions->changefreq = $versionsChangefreq;
				$versions->priority   = $versionsPriority;
				$versions->exclude    = (!empty($versionsExclude)) ? $versionsExclude : false;
				$versions->alternates = ($multilanguage) ? [] : false;

				if ($project->alternates !== false)
				{
					foreach ($this->translates['all'] as $code)
					{
						$project->alternates[$code] = RouteHelper::getProjectRoute($slug, $catslug)
							. '&lang=' . $code;

						$versions->alternates[$code] = RouteHelper::getVersionsRoute($slug, $catslug)
							. '&lang=' . $code;
					}
				}

				// Add project to array
				if ($this->params->get('project_enable'))
				{
					$urls[] = $project;
				}

				// Add versions to array
				if ($this->params->get('versions_enable'))
				{
					$urls[] = $versions;
				}
			}
		}

		// Add versions to sitemap
		if ($this->params->get('version_enable'))
		{
			$query = $db->getQuery(true)
				->select(['v.id', 'v.major', 'v.minor', 'v.patch', 'v.hotfix', 'v.tag', 'v.stage', 'v.state'])
				->from($db->quoteName('#__swjprojects_versions', 'v'))
				->where($db->quoteName('p.visible'). ' = '. $db->quote('1'))
				->group(['v.id', 't_v.language']);

			// Join over projects
			$query->select(['p.id as project_id', 'p.alias as project_alias', 'p.state as project_state'])
				->leftJoin($db->quoteName('#__swjprojects_projects', 'p') . ' ON p.id = v.project_id');

			// Join over categories
			$query->select(['c.id as category_id', 'c.alias as category_alias', 'c.state as category_state'])
				->leftJoin($db->quoteName('#__swjprojects_categories', 'c') . ' ON c.id = p.catid');

			// Join over translates
			$query->select(['t_p.title as project_title', 't_v.language', 't_v.metadata']);
			if ($multilanguage)
			{
				$query->leftJoin($db->quoteName('#__swjprojects_translate_versions', 't_v')
					. '  ON t_v.id = v.id AND ' . $db->quoteName('t_v.language') . 'IN (' . implode(',', $all) . ')');
			}
			else
			{
				$query->leftJoin($db->quoteName('#__swjprojects_translate_versions', 't_v')
					. '  ON t_v.id = v.id AND ' . $db->quoteName('t_v.language') . ' = ' . $db->quote($current));
			}
			$query->leftJoin($db->quoteName('#__swjprojects_translate_projects', 't_p')
				. '  ON t_p.id = p.id AND t_p.language = t_v.language');

			// Join over default translates
			$query->select(array('td_p.title as project_default_title'))
				->leftJoin($db->quoteName('#__swjprojects_translate_projects', 'td_p')
					. ' ON td_p.id = p.id AND ' . $db->quoteName('td_p.language') . ' = ' . $db->quote($default));

			$rows       = $db->setQuery($query)->loadObjectList();
			$changefreq = $this->params->get('version_changefreq', $config->get('changefreq', 'weekly'));
			$priority   = $this->params->get('version_priority', $config->get('priority', '0.5'));

			foreach ($rows as $row)
			{
				// Prepare title attribute
				$projectTitle = (!empty($row->project_title)) ? $row->project_title : $row->project_default_title;
				if (empty($projectTitle))
				{
					$projectTitle = $row->project_alias;
				}

				$projectVersion = $row->major . '.' . $row->minor . '.' . $row->patch;
				if (\property_exists($row, 'hotfix') && !empty($row->hotfix))
				{
					$projectVersion .= '.' . $row->hotfix;
				}

				$title = $projectTitle . ' ' . $projectVersion;
				if ($row->tag !== 'stable')
				{
					$title .= ' ' . Text::_('PLG_JLSITEMAP_SWJPROJECTS_TYPES_VERSION_TAG_' . $row->tag);
					if ($row->tag !== 'dev' && !empty($row->stage))
					{
						$title .= ' ' . $row->stage;
					}
				}

				// Prepare loc attribute
				$pojectslug = $row->project_id . ':' . $row->project_alias;
				$catslug    = $row->category_id . ':' . $row->category_alias;
				$loc        = RouteHelper::getVersionRoute($row->id, $pojectslug, $catslug);
				if ($multilanguage)
				{
					$loc .= '&lang=' . $row->language;
				}

				// Prepare exclude attribute
				$metadata   = new Registry($row->metadata);
				$exclude    = [];
				$siteRobots = $metadata->get('robots', $config->get('siteRobots'));
				if (!empty($siteRobots) && \preg_match('/noindex/', $siteRobots))
				{
					$exclude[] = ['type' => Text::_('PLG_JLSITEMAP_SWJPROJECTS_EXCLUDE_VERSION'),
					              'msg'  => Text::_('PLG_JLSITEMAP_SWJPROJECTS_EXCLUDE_VERSION_ROBOTS')];
				}
				if ($row->state != 1)
				{
					$exclude[] = [
						'type' => Text::_('PLG_JLSITEMAP_SWJPROJECTS_EXCLUDE_VERSION'),
						'msg'  => ($row->state == -1)
							? Text::_('PLG_JLSITEMAP_SWJPROJECTS_EXCLUDE_VERSION_TRASH')
							: Text::_('PLG_JLSITEMAP_SWJPROJECTS_EXCLUDE_VERSION_UNPUBLISH')
					];
				}
				if ($row->project_state != 1)
				{
					$exclude[] = [
						'type' => Text::_('PLG_JLSITEMAP_SWJPROJECTS_EXCLUDE_PROJECT'),
						'msg'  => ($row->project_state == -1)
							? Text::_('PLG_JLSITEMAP_SWJPROJECTS_EXCLUDE_PROJECT_TRASH')
							: Text::_('PLG_JLSITEMAP_SWJPROJECTS_EXCLUDE_PROJECT_UNPUBLISH')
					];
				}
				if ($row->category_state != 1)
				{
					$exclude[] = [
						'type' => Text::_('PLG_JLSITEMAP_SWJPROJECTS_EXCLUDE_CATEGORY'),
						'msg'  => ($row->state == -1)
							? Text::_('PLG_JLSITEMAP_SWJPROJECTS_EXCLUDE_CATEGORY_TRASH')
							: Text::_('PLG_JLSITEMAP_SWJPROJECTS_EXCLUDE_CATEGORY_UNPUBLISH')
					];
				}

				// Prepare project object
				$version             = new \stdClass();
				$version->type       = Text::_('PLG_JLSITEMAP_SWJPROJECTS_TYPES_VERSION');
				$version->title      = $title;
				$version->loc        = $loc;
				$version->changefreq = $changefreq;
				$version->priority   = $priority;
				$version->exclude    = (!empty($exclude)) ? $exclude : false;
				$version->alternates = ($multilanguage) ? array() : false;

				if ($version->alternates !== false)
				{
					foreach ($this->translates['all'] as $code)
					{
						$version->alternates[$code] = RouteHelper::getVersionRoute($row->id, $pojectslug, $catslug)
							. '&lang=' . $code;
					}
				}

				// Add version to array
				$urls[] = $version;
			}
		}

		$event->setArgument(0, $urls);
	}

}