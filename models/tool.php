<?php
/*
 * HUBzero CMS
 *
 * Copyright 2005-2015 HUBzero Foundation, LLC.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * HUBzero is a registered trademark of Purdue University.
 *
 * @package   hubzero-cms
 * @author    Anthony Fuentes <fuentesa@purdue.edu>
 * @copyright Copyright 2005-2015 HUBzero Foundation, LLC.
 * @license   http://opensource.org/licenses/MIT MIT
 */

namespace Components\Toolbox\Models;

$toolboxPath = Component::path('com_toolbox');
$tagsPath = Component::path('com_tags');

require_once "$toolboxPath/helpers/toolAuthHelper.php";
require_once "$tagsPath/models/tag.php";
require_once "$tagsPath/models/cloud.php";
require_once "$toolboxPath/models/download.php";
require_once "$toolboxPath/models/link.php";
require_once "$toolboxPath/models/review.php";
require_once "$toolboxPath/models/toolsRelationship.php";
require_once "$toolboxPath/models/toolsType.php";

use Components\Toolbox\Helpers\ToolAuthHelper;
use Components\Tags\Models\Cloud;
use Hubzero\Database\Relational;
use stdClass;

class Tool extends Relational implements \Hubzero\Search\Searchable
{

	/*
	 * Records table
	 *
	 * @var string
	 */
	protected $table = '#__toolbox_tools';

	/*
	 * Attributes to be populated on record creation
	 *
	 * @var array
	 */
	public $initiate = ['created'];

	/*
	 * Attribute validation
	 *
	 * @var  array
	 */
	protected $rules = [
		'duration' => 'positive',
		'external_cost' => 'positive',
		'kinesthetic' => 'positive',
		'name' => 'notempty',
		'source' => 'notempty',
		'subgroup_size' => 'notempty'
	];

	/*
	 * Instantiates a Tool model
	 *
	 * @return   object
	 */
	public static function blank()
	{
		$tool = parent::blank();

		$defaults = [
			'minimum_participants' => '0',
			'suggested_participants' => '0',
			'maximum_participants' => '0',
			'duration' => '0',
			'cost' => 0
		];

		$tool->set($defaults);

		return $tool;
	}

	/*
	 * Get tools with IDs other than the provided tool(s)
	 *
	 *  @param    array    $tools   The tools to exclude from query
	 *  @return   object
	 */
	public static function otherTools($tools)
	{
		$toolIds = array_map(function($tool) {
			return $tool->get('id');
		}, $tools);

		$otherTools = self::all()
			->whereNotIn('id', $toolIds)
			->whereEquals('published', 1)
			->rows();

		return $otherTools;
	}

	/*
	 * Returns an array containing the associated types' IDs
	 *
	 * @return   array
	 */
	public function typeIds()
	{
		$types = $this->types()->rows()->toArray();

		$typeIds = array_map(function($type) {
			return $type['id'];
		}, $types);

		return $typeIds;
	}

	/*
	 * Returns associated Type records
	 *
	 * @return   object
	 */
	public function types()
	{
		$toolTypeModelName = 'Components\Toolbox\Models\ToolType';
		$associativeTable = '#__toolbox_tools_types';
		$primaryKey = 'tool_id';
		$foreignKey = 'type_id';

		$types = $this->manyToMany(
			$toolTypeModelName,
			$associativeTable,
			$primaryKey,
			$foreignKey
		);

		return $types;
	}

	/*
	 * Returns associated type join records
	 *
	 * @return   object
	 */
	protected function _typeJoins()
	{
		$joinModelName = 'Components\Toolbox\Models\ToolsType';

		$typeJoins = $this->oneToMany($joinModelName, 'tool_id');

		return $typeJoins;
	}

	/*
	 * Returns the IDs of related tool records
	 *
	 * @return   array
	 */
	public function relatedToolsIds()
	{
		$relatedTools = $this->relatedTools()->rows();
		$relatedToolsIds = [];

		foreach ($relatedTools as $relatedTool)
		{
			$relatedToolsIds[] = $relatedTool->get('id');
		}

		return $relatedToolsIds;
	}

	/*
	 * Returns associated Tool records
	 *
	 * @return   object
	 */
	public function relatedTools()
	{
		$toolModelName = 'Components\Toolbox\Models\Tool';
		$associativeTable = '#__toolbox_tools_relationships';
		$primaryKey = 'origin_id';
		$foreignKey = 'related_id';

		$relatedTools = $this->manyToMany(
			$toolModelName,
			$associativeTable,
			$primaryKey,
			$foreignKey
		);

		return $relatedTools;
	}

	/*
	 * Returns tool join records where this tool is the origin tool
	 *
	 * @return   object
	 */
	protected function _toolOriginJoins()
	{
		$joinModelName = 'Components\Toolbox\Models\ToolsRelationship';

		$toolJoins = $this->oneToMany($joinModelName, 'origin_id');

		return $toolJoins;
	}

	/*
	 * Returns tool join records where this tool is the related tool
	 *
	 * @return   object
	 */
	protected function _toolRelatedJoins()
	{
		$joinModelName = 'Components\Toolbox\Models\ToolsRelationship';

		$toolJoins = $this->oneToMany($joinModelName, 'related_id');

		return $toolJoins;
	}

	/*
	 * Returns the IDs of associated Tag records
	 *
	 * @return   array
	 */
	public function tagsIds()
	{
		$tags = $this->tags()->rows();
		$tagsIds = [];

		foreach ($tags as $tag)
		{
			$tagsIds[] = $tag->get('id');
		}

		return $tagsIds;
	}

	/*
	 * Returns associated Tag records
	 *
	 * @return   object
	 */
	public function tags()
	{
		$tagModelName = 'Components\Tags\Models\Tag';
		$associativeTable = '#__tags_object';
		$primaryKey = 'objectid';
		$foreignKey = 'tagid';

		$tags = $this->manyToMany(
			$tagModelName,
			$associativeTable,
			$primaryKey,
			$foreignKey
		);

		return $tags;
	}

	/*
	 * Returns tag join records
	 *
	 * @return   object
	 */
	protected function _tagJoins()
	{
		$joinModelName = 'Components\Tags\Models\Objct';

		$tagJoins = $this->oneToMany($joinModelName, 'objectid');

		return $tagJoins;
	}

	/*
	 * Returns associated Download records that are synchornized
	 *
	 * @return   object
	 */
	public function synchronizedDownloads()
	{
		$downloads = $this->downloads();

		$synchronizedDownloads = $downloads
			->whereEquals('desynchronized', 0);

		return $synchronizedDownloads;
	}

	/*
	 * Returns associated Download records
	 *
	 * @return   object
	 */
	public function downloads()
	{
		$downloadModelName = 'Components\Toolbox\Models\Download';

		$downloads = $this->oneToMany($downloadModelName, 'tool_id');

		return $downloads;
	}

	/**
	 * Namespace used for solr Search
	 *
	 * @return  string
	 */
	public static function searchNamespace()
	{
		$searchNamespace = 'toolbox';
		return $searchNamespace;
	}

	/**
	 * Generate solr search Id
	 *
	 * @return  string
	 */
	public function searchId()
	{
		$searchId = self::searchNamespace() . '-' . $this->id;
		return $searchId;
	}

	/**
	 * Generate search document for Solr
	 *
	 * @return  array
	 */
	public function searchResult()
	{
		if ($this->get('published') != 1 || $this->get('archived') == 1)
		{
			return false;
		}
		$toolbox = new stdClass;
		$toolbox->title = $this->get('name');
		$toolbox->hubtype = self::searchNamespace();
		$toolbox->id = $this->searchId();
		$toolbox->description = $this->get('learning_objectives');
		$toolbox->source_s = $this->get('source');
		$tags = $this->tags;
		if (!empty($tags))
		{
			foreach ($tags as $tag)
			{
				$title = $tag->get('raw_tag', '');
				$description = $tag->get('tag', '');
				$label = $tag->get('label', '');
				$toolbox->tags[] = array(
					'id' => 'tag-' . $tag->id,
					'title' => $title,
					'description' => $description,
					'access_level' => $tag->admin == 0 ? 'public' : 'private',
					'type' => 'toolbox-tag',
					'badge_b' => $label == 'badge' ? true : false
				);
			}
		}
		$toolbox->url = rtrim(Request::root(), '/') . Route::urlForClient('site', 'index.php?option=com_toolbox&id=' . $this->get('id'));
		return $toolbox;
	}

	/**
	 * Get total number of records that will be indexed by Solr.
	 *
	 * @return integer
	 */
	public static function searchTotal()
	{
		$total = self::all()->total();
		return $total;
	}

	/**
	 * Get records to be included in solr index
	 *
	 * @param   integer  $limit
	 * @param   integer  $offset
	 * @return  object   Hubzero\Database\Rows
	 */
	public static function searchResults($limit, $offset = 0)
	{
		return self::all()->start($offset)->limit($limit)->rows();
	}

	/*
	 * Returns associated Link records
	 *
	 * @return   object
	 */
	public function links()
	{
		$linkModelName = 'Components\Toolbox\Models\Link';

		$links = $this->oneToMany($linkModelName, 'tool_id');

		return $links;
	}

	/*
	 * Returns associated Review records
	 *
	 * @return   object
	 */
	public function reviews()
	{
		$reviewModelName = 'Components\Toolbox\Models\Review';
		$shifter = 'scope';

		$reviews = $this->oneShiftsToMany(
			$reviewModelName,
			'scope_id',
			$shifter
		);

		return $reviews;
	}

	/*
	 * Translates duration into descriptive phrase
	 *
	 * @return   string
	 */
	public function durationDescription()
	{
		$minutes = $this->get('duration');
		$hours = floor($minutes / 60);
		$remainingMinutes = $minutes - ($hours * 60);
		$durationDescription = "";

		if ($hours > 0)
		{
			$durationDescription .= "$hours " . Lang::txt('COM_TOOLBOX_COMMON_HOUR');

			if ($hours > 1)
			{
				$durationDescription .= "s";
			}
		}

		if ($remainingMinutes > 0)
		{
			if ($hours > 0)
			{
				$durationDescription .= " " . Lang::txt('COM_TOOLBOX_COMMON_AND') . " ";
			}

			$durationDescription .= "$remainingMinutes " . Lang::txt('COM_TOOLBOX_COMMON_MINUTE');

			if ($remainingMinutes > 1)
			{
				$durationDescription .= "s";
			}
		}

		if ($durationDescription == '')
		{
			$durationDescription = Lang::txt('COM_TOOLBOX_TOOL_NO_DURATION');
		}

		return $durationDescription;
	}

	/*
	 * Constructs a tag cloud using the tool's tags
	 *
	 * @return   string   HTML
	 */
	public function tagsCloud()
	{
		$id = $this->get('id');
		$table = $this->table;
		$scope = ltrim($table, '#__');

		$cloud = new Cloud($id, $scope);
		$tagCloud = $cloud->render();

		return $tagCloud;
	}

	/*
	 * Translates external cost boolean into descriptive phrase
	 *
	 * @return   string
	 */
	public function costDescription()
	{
		if ($this->get('external_cost'))
		{
			$costDescription = Lang::txt('COM_TOOLBOX_TOOL_EXTERNAL_COST');
		}
		else
		{
			$costDescription = '';
		}

		return $costDescription;
	}

	/*
	 * Translates subgroup size into descriptive phrase
	 *
	 * @return   array
	 */
	public function subgroupSizeDescription()
	{
		$subgroupSize = $this->get('subgroup_size');

		if (!!$subgroupSize)
		{
			$subgroupSizeKey = $this->getSubgroupSizes();
			$subgroupSizeDescription = $subgroupSizeKey[$subgroupSize];
		}
		else
		{
			$subgroupSizeDescription = Lang::txt('COM_TOOLBOX_TOOL_NO_SUBGROUP_SIZE');
		}

		return $subgroupSizeDescription;
	}

	/*
	 * Returns the set of possible subgroup sizes
	 *
	 * @return   array
	 */
	public function getSubgroupSizes()
	{
		return [
				'pairs' => Lang::txt('COM_TOOLBOX_SUBGROUP_PAIRS'),
				'small' => Lang::txt('COM_TOOLBOX_SUBGROUP_SMALL'),
				'large' => Lang::txt('COM_TOOLBOX_SUBGROUP_LARGE'),
				'whole' => Lang::txt('COM_TOOLBOX_SUBGROUP_WHOLE')
			];
	}

	/*
	 * Returns Tool model scope
	 *
	 * @return   string
	 */
	public function getScope()
	{
		$modelName = $this->getModelName();
		$scope = strtolower($modelName);

		return $scope;
	}

	/*
	 * Destroys tool and dependent associations
	 *
	 * @return   bool
	 */
	public function destroy()
	{
		$associations = $this->_getAllAssociations();

		foreach ($associations as $associatedRecords)
		{
			$associatedRecords->destroyAll();
		}

		$toolDestroyed = parent::destroy();

		return $toolDestroyed;
	}

	/*
	 * Returns ID of the associated User
	 *
	 * @return   int
	 */
	public function userId()
	{
		$user = $this->user()->rows();
		$userId = $user->get('id');

		return $userId;
	}

	/*
	 * Returns associated User
	 *
	 * @return   object
	 */
	public function user()
	{
		$userModelName = 'Hubzero\User\User';
		$foreignKey = 'user_id';

		$user = $this->belongsToOne(
			$userModelName,
			$foreignKey
		);

		return $user;
	}

	/*
	 * Returns all associated records in the form of an array with a row object
	 * per associated type
	 *
	 * @return   array
	 */
	protected function _getAllAssociations()
	{
		$associations = [];

		$associations[] = $this->downloads();
		$associations[] = $this->links();
		$associations[] = $this->_tagJoins();
		$associations[] = $this->_toolOriginJoins();
		$associations[] = $this->_toolRelatedJoins();
		$associations[] = $this->reviews();
		$associations[] = $this->_typeJoins();

		$associations = array_map(function($associatedType) {
			return $associatedType->rows();
		}, $associations);

		return $associations;
	}

	/*
	 * Unpublishes the tool if current user is not an admin
	 *
	 * @return   void
	 */
	public function unpublishIfNotAdmin()
	{
		$userIsAdmin = ToolAuthHelper::currentIsAuthorized('core.manage');

		if ($this->get('published') && !$userIsAdmin)
		{
			$this->set('published', 0);

			$this->save();
		}
	}

}
