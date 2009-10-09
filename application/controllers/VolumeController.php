<?php
/**
 * Copyright 2007, 2008 Yuri Timofeev tim4dev@gmail.com
 *
 * This file is part of Webacula.
 *
 * Webacula is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Webacula is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Webacula.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author Yuri Timofeev <tim4dev@gmail.com>
 * @package webacula
 * @license http://www.gnu.org/licenses/gpl-3.0.html GNU Public License
 *
 */

/* Zend_Controller_Action */
require_once 'Zend/Controller/Action.php';

class VolumeController extends Zend_Controller_Action
{

    protected $config_webacula;

	function init()
	{
		$this->view->baseUrl = $this->_request->getBaseUrl();
		// load model
		Zend_Loader::loadClass('Media');
		Zend_Loader::loadClass('Pool');

		$this->view->translate = Zend_Registry::get('translate');
		Zend_Loader::loadClass('MyClass_SendEmail');
        $this->config_webacula = Zend_Registry::get('config_webacula');
	}

    function findNameAction()
    {
    	// http://localhost/webacula/volume/find-name/volname/pool.file.7d.0005
    	$volname = addslashes( $this->_request->getParam('volname') );
    	$order   = addslashes(trim( $this->_request->getParam('order', 'VolumeName') ));
    	$this->view->volname = $volname;
    	if ( $volname )	{
    		$this->view->title = $this->view->translate->_("Volume") . " " . $volname;
    		$media = new Media();
			$this->view->result = $media->getByName($volname, $order);
    	}
    	else
    		$this->view->result = null;
    }


    function findPoolIdAction()
    {
    	// http://localhost/webacula/volume/find-pool-id/id/2/name/pool.file.7d
    	$pool_id   = intval( $this->_request->getParam('id') );
    	$pool_name = addslashes( $this->_request->getParam('name') );
    	$order     = addslashes(trim( $this->_request->getParam('order', 'VolumeName') ));

    	$this->view->pool_id   = $pool_id;
    	$this->view->pool_name = $pool_name;
    	if ( $pool_id  )	{
    		$this->view->title = $this->view->translate->_("Pool") . " " . $pool_name;
    		$media = new Media();
			$this->view->result = $media->getById($pool_id, $order);
    	}
    	else
    		$this->view->result = null;
    }

    /**
     * Volumes with errors/problems
     */
    function problemAction()
    {
		$this->view->titleProblemVolumes = $this->view->translate->_("Volumes with errors");
		$order = addslashes(trim( $this->_request->getParam('order', 'VolumeName') ));
		// get data from model
    	$media = new Media();
    	$ret = $media->GetProblemVolumes();
    	$this->view->resultProblemVolumes = $ret->fetchAll(null, $order);
    }

    /**
     * Volumes with errors/problems
     */
    function problemDashboardAction()
    {
    	$this->_helper->viewRenderer->setResponseSegment('volume_problem');
		$this->view->titleProblemVolumes = $this->view->translate->_("Volumes with errors");
		$order = addslashes(trim( $this->_request->getParam('order', 'VolumeName') ));
		// get data from model
    	$media = new Media();
    	$ret = $media->GetProblemVolumes();
    	$this->view->resultProblemVolumes = $ret->fetchAll(null, $order);
    }

    /*
     * Volume detail info
     */
    function detailAction()
    {
        // http://localhost/webacula/volume/detail/mediaid/2/
        $media_id = intval( $this->_request->getParam('mediaid') );
        if ( $media_id  )    {
            $this->view->title = $this->view->translate->_("Detail Volume") . " " . $media_id;
            $media = new Media();
            $this->view->result = $media->detail($media_id);
            $pools = new Pool();
            $this->view->pools = $pools->fetchAll();
        }
        else
            $this->view->result = null;
    }

    function updateAction()
    {
        // ****************************** UPDATE record **********************************
        $media_id    = trim( $this->_request->getPost('mediaid') );
        $pool_id     = trim( $this->_request->getPost('poolid') );
        $volume_name = trim( $this->_request->getPost('volumename') );
        if ( !empty($media_id) && !empty($pool_id) ) {
            // update record into database
            $table = new Media();
            $data = array(
                'poolid'        => $pool_id,
                'volstatus'     => trim( $this->_request->getPost('volstatus') ),
                'volretention'  => (int)trim( $this->_request->getPost('volretention') ) * 86400,
                'recycle'       => trim( $this->_request->getPost('recycle') ),
                'slot'          => trim( $this->_request->getPost('slot') ),
                'inchanger'     => trim( $this->_request->getPost('inchanger') ),
                'maxvoljobs'    => trim( $this->_request->getPost('maxvoljobs') ),
                'maxvolfiles'   => trim( $this->_request->getPost('maxvolfiles') ),
                'comment'       => trim( $this->_request->getPost('comment') )
            );

            $where = $table->getAdapter()->quoteInto('MediaId = ?', $media_id);
            $res = $table->update($data, $where);

            // send email
            if ( $res ) {
                $email = new MyClass_SendEmail();
                $email->mySendEmail(
                    $this->config_webacula->email->from,
                    $this->config_webacula->email->to_admin,
                    $this->view->translate->_('Media Id') . ': ' . $media_id . "\n" .
                    $this->view->translate->_('Volume Name') . ': ' . $volume_name . "\n\n",
                    $this->view->translate->_('Webacula : Updated Volume parameters') );
            }
        }
        $this->_redirect("/volume/detail/mediaid/$media_id");
        return;
    }

}