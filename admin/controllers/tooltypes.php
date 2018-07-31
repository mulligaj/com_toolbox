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

namespace Components\Toolbox\Admin\Controllers;

$toolboxPath = Component::path('com_toolbox');

require_once "$toolboxPath/admin/helpers/filterHelper.php";
require_once "$toolboxPath/models/toolType.php";

use \Components\Toolbox\Admin\Helpers\FilterHelper;
use \Components\Toolbox\Admin\Helpers\Permissions;
use \Components\Toolbox\Models\ToolType;
use Hubzero\Component\AdminController;
use Hubzero\Database\Query;

class ToolTypes extends AdminController
{

	/*
	 * Task mapping
	 *
	 * @var  array
	 */
	protected $_taskMap = [
		'__default' => 'list'
	];

	/*
	 * Administrator toolbar title
	 *
	 * @var  string
	 */
	protected static $_toolbarTitle = 'Toolbox';

	/*
	 * Renders types list view
	 *
	 * @return   void
	 */
	public function listTask()
	{
		$component = $this->_option;
		$controller = $this->_controller;
		$filters = FilterHelper::getFilters($component, $controller);
		$permissions = Permissions::getActions();

		$types = ToolType::all()
			->whereEquals('archived', 0);

		// filter types by name
		if (!empty($filters['search']))
		{
			$types->where('name', ' like ', "%{$filters['search']}%");
		}

		// sort types based on given criteria
		$types = $types->order($filters['sort'], $filters['sort_Dir'])
			->paginated('limitstart', 'limit');

		$this->view
			->set('filters', $filters)
			->set('permissions', $permissions)
			->set('title', static::$_toolbarTitle)
			->set('types', $types)
			->display();
	}

	/*
	 * Archives given types
	 *
	 * @return   void
	 */
	public function archiveTask()
	{
		Request::checkToken();

		$typesIds = Request::getArray('typesIds');

		$typesTable = (new ToolType())->getTableName();

		$updateQuery = (new Query())
			->update($typesTable)
			->set(['archived' => 1])
			->whereIn('id', $typesIds);

		$typesUpdated = $updateQuery->execute();

		if ($typesUpdated)
		{
			$this->_successfulArchive();
		}
		else
		{
			$this->_failedArchive();
		}
	}

	/*
	 * Redirects to types list w/ success message
	 *
	 * @return   void
	 */
	protected function _successfulArchive()
	{
		$forwardingUrl = Request::getString('forward');

		App::redirect(
			$forwardingUrl,
			Lang::txt('COM_TOOLBOX_TYPES_ARCHIVE_SUCCESS'),
			'passed'
		);
	}

	/*
	 * Redirects to types list w/ error message
	 *
	 * @return   void
	 */
	protected function _failedArchive()
	{
		$originUrl = Request::getString('origin');
		$errorMessage = Lang::txt('COM_TOOLBOX_TYPES_ARCHIVE_FAILURE');

		Notify::error($errorMessage);

		App::redirect($originUrl);
	}

	/*
	 * Renders the view for creating a new tool type
	 *
	 * @return  void
	 */
	public function newTask()
	{
		$type = new ToolType();

		$this->view
			->set('type', $type)
			->display();
	}

	/*
	 * Creates new tool type record using given data
	 *
	 * @return  void
	 */
	public function createTask()
	{
		Request::checkToken();

		$component = $this->_option;
		$controller = $this->_controller;
		$typeData = Request::getArray('type');
		$typeListUrl = Route::url(
			"/administrator/index.php?option=$component&controller=$controller&task=list"
		);

		$type = ToolType::blank();

		$type->set($typeData);

		if ($type->save())
		{
			$message = Lang::txt('COM_TOOLBOX_TYPES_CREATE_SUCCESS');
			$outcome = 'success';
		}
		else
		{
			$message = Lang::txt('COM_TOOLBOX_TYPES_CREATE_FAILURE') . '<br/>';
			foreach ($type->getErrors() as $error)
			{
				$message .= "<br/>• $error";
			}
			$outcome = 'error';
		}

		App::redirect(
			$typeListUrl,
			$message,
			$outcome
		);
	}

}
