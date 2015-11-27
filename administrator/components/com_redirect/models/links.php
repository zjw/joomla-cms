<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_redirect
 *
 * @copyright   Copyright (C) 2005 - 2015 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

/**
 * Methods supporting a list of redirect links.
 *
 * @since  1.6
 */
class RedirectModelLinks extends JModelList
{
	/**
	 * Constructor.
	 *
	 * @param   array  $config  An optional associative array of configuration settings.
	 *
	 * @since   1.6
	 */
	public function __construct($config = array())
	{
		if (empty($config['filter_fields']))
		{
			$config['filter_fields'] = array(
				'id', 'a.id',
				'old_url', 'a.old_url',
				'new_url', 'a.new_url',
				'referer', 'a.referer',
				'hits', 'a.hits',
				'created_date', 'a.created_date',
				'published', 'a.published',
			);
		}

		parent::__construct($config);
	}
	/**
	 * Removes all of the unpublished redirects from the table.
	 *
	 * @return  boolean result of operation
	 *
	 * @since   3.5
	 */
	public function purge()
	{
		$db = $this->getDbo();

		$query = $db->getQuery(true);

		$query->delete('#__redirect_links')->where($db->qn('published') . '= 0');

		$db->setQuery($query);

		try
		{
			$db->execute();
		}
		catch (Exception $e)
		{
			return false;
		}

		return true;
	}

	/**
	 * Method to auto-populate the model state.
	 *
	 * Note. Calling getState in this method will result in recursion.
	 *
	 * @param   string  $ordering   An optional ordering field.
	 * @param   string  $direction  An optional direction (asc|desc).
	 *
	 * @return  void
	 *
	 * @since   1.6
	 */
	protected function populateState($ordering = null, $direction = null)
	{
		// Load the filter state.
		$search = $this->getUserStateFromRequest($this->context . '.filter.search', 'filter_search');
		$this->setState('filter.search', $search);

		$state = $this->getUserStateFromRequest($this->context . '.filter.state', 'filter_state', '', 'string');
		$this->setState('filter.state', $state);

		// Load the parameters.
		$params = JComponentHelper::getParams('com_redirect');
		$this->setState('params', $params);

		// List state information.
		parent::populateState('a.old_url', 'asc');
	}

	/**
	 * Method to get a store id based on model configuration state.
	 *
	 * This is necessary because the model is used by the component and
	 * different modules that might need different sets of data or different
	 * ordering requirements.
	 *
	 * @param   string  $id  A prefix for the store id.
	 *
	 * @return  string  A store id.
	 *
	 * @since   1.6
	 */
	protected function getStoreId($id = '')
	{
		// Compile the store id.
		$id .= ':' . $this->getState('filter.search');
		$id .= ':' . $this->getState('filter.state');

		return parent::getStoreId($id);
	}

	/**
	 * Build an SQL query to load the list data.
	 *
	 * @return  JDatabaseQuery
	 *
	 * @since   1.6
	 */
	protected function getListQuery()
	{
		// Create a new query object.
		$db = $this->getDbo();
		$query = $db->getQuery(true);

		// Select the required fields from the table.
		$query->select(
			$this->getState(
				'list.select',
				'a.*'
			)
		);
		$query->from($db->quoteName('#__redirect_links') . ' AS a');

		// Filter by published state
		$state = $this->getState('filter.state');

		if (is_numeric($state))
		{
			$query->where('a.published = ' . (int) $state);
		}
		elseif ($state === '')
		{
			$query->where('(a.published IN (0,1,2))');
		}

		// Filter the items over the search string if set.
		$search = $this->getState('filter.search');

		if (!empty($search))
		{
			if (stripos($search, 'id:') === 0)
			{
				$query->where('a.id = ' . (int) substr($search, 3));
			}
			else
			{
				$search = $db->quote('%' . str_replace(' ', '%', $db->escape(trim($search), true) . '%'));
				$query->where(
					'(' . $db->quoteName('old_url') . ' LIKE ' . $search .
					' OR ' . $db->quoteName('new_url') . ' LIKE ' . $search .
					' OR ' . $db->quoteName('comment') . ' LIKE ' . $search .
					' OR ' . $db->quoteName('referer') . ' LIKE ' . $search . ')'
				);
			}
		}

		// Add the list ordering clause.
		$query->order($db->escape($this->getState('list.ordering', 'a.old_url')) . ' ' . $db->escape($this->getState('list.direction', 'ASC')));

		return $query;
	}

	/**
	 * Add the entered URLs into the database
	 *
	 * @param   array  $batch_urls  Array of URLs to enter into the database
	 *
	 * @return  array  An array of URLs not entered into the database (duplicates).
	 */
	public function batchProcess($batch_urls)
	{
		$db    = JFactory::getDbo();
		$goodUrls = array();
		$badUrls = array();

		$query = $db->getQuery(true)
			->select($db->quoteName('id'))
			->from('#__redirect_links');

		foreach ($batch_urls as $batch_url)
		{
			// Source URLs need to have the correct URL format to work properly
			if (strpos($batch_url[0], JUri::root()) === false)
			{
				$old_url = JUri::root() . $batch_url[0];
			}
			else
			{
				$old_url = $batch_url[0];
			}

			/*
			 * old_url in the database is varchar(255).  Truncate it here so that a search for
			 * duplicates works properly.
			 */
			$old_url = substr($old_url, 0, 255);

			// Destination URL can also be an external URL
			if (!empty($batch_url[1]))
			{
				$new_url = $batch_url[1];
			}
			else
			{
				$new_url = '';
			}

			// Check if old_url already exists.
			$query->clear('where')
				->where($db->quoteName('old_url') . ' = ' . $db->quote($old_url));
			$db->setQuery($query);
			$result = $db->loadResult();

			if (!is_null($result))
			{
				/*
				 * A different entry already exists for old_url.  We don't permit
				 * duplicate entries for old_url.  This used to be enforced with a UNIQUE
				 * KEY on old_url in the database, but the key length had to be reduced,
				 * so it could no longer be UNIQUE.  Consequently, we're checking for
				 * uniqueness here.
				 */
				$badUrls[] = $old_url;
			}
			else
			{
				$goodUrls[] = array($old_url, $new_url);
			}
		}

		if (!empty($goodUrls))
		{
			$query = $db->getQuery(true);

			$columns = array(
				$db->quoteName('old_url'),
				$db->quoteName('new_url'),
				$db->quoteName('referer'),
				$db->quoteName('comment'),
				$db->quoteName('hits'),
				$db->quoteName('published'),
				$db->quoteName('created_date')
			);

			$query->columns($columns);

			foreach ($goodUrls as $goodUrl)
			{
				$query->insert($db->quoteName('#__redirect_links'), false)
					->values(
						$db->quote($goodUrl[0]) . ', ' . $db->quote($goodUrl[1]) . ' ,' . $db->quote('') . ', ' . $db->quote('') . ', 0, 0, ' .
						$db->quote(JFactory::getDate()->toSql())
					);
			}

			$db->setQuery($query);
			$db->execute();
		}

		return $badUrls;
	}
}
