<?php

require_once 'BaseController.php';
/**
 * Backend Ticket Controller Class
 *
 * @author
 * @since
 * @see Tixee_Controller_Action
 * @link
 */
class Backend_TicketController extends Base_Controller_Action
{
     /**
     * ������
     */
    public function init()
    {
       // Base�p��
       parent::init();
       // �摜�p
       $contextSwitch = $this->_helper->getHelper('contextSwitch');
       $contextSwitch->addActionContext('tmp-upload-image', 'xml')->initContext('xml');
    }

    /**
     * �A�N�V�����O����
     *
     * �A�N�V�������s�O�ɌĂяo�����B
     *
     * @access public
     */
    public function preDispatch() {

        // Base�p��
        parent::preDispatch();

        if(isset($this->paramHash['userId'])){
                // �T�u���j���[�̃Z�b�g
                $this->view->submenu = 'user-submenu.phtml';
        }
        elseif(isset($this->paramHash['ticketId'])) {
        	$this->view->submenu = 'ticket-submenu.phtml';
        }
    }

     /**
     * �`�P�b�g��񌟍�
     */
    public function indexAction()
    {
        // �`�P�b�g��񏉊���
        $tickets = array();

        $currentPage = '';

        $getParam = '';

        // �t�H�[�����Z�b�g����
        $form = $this->_createZendFormIni($this->request->getActionName());

        $ticketQueryBuilder = $this->_em->getRepository('Ticket')->createQueryBuilder('t');
        $ticketQueryBuilder->leftjoin('t.event' ,'e');

        // �Z�b�V�����̎擾
        $backendTicketSearch = new Zend_Session_Namespace('backendTicketSearch');

        if(isset($backendTicketSearch->postHash) && $this->request->isGet()){

            if(isset($this->paramHash['s']) && $this->paramHash['s'] == 'clear'){

                // �Z�b�V�����j��
                unset($backendTicketSearch->postHash);
            }else{
                // �Z�b�V�������̎擾
                $this->postHash = $backendTicketSearch->postHash;
            }
        }

        // POST
        if(!empty($this->postHash) and $form->isValid($this->postHash)){

            // �X�e�[�^�X��������
            $checkStatus = array();

            // ���ݎ���
            $time = new Datetime();

            // �`�P�b�gID
            if(!empty($this->postHash['id'])){
                $ticketQueryBuilder->andWhere('t.id = ?1');
                $ticketQueryBuilder->setParameter(1, $this->postHash['id']);
            }

            // �C�x���gID
            if(!empty($this->postHash['eventName'])){
                $ticketQueryBuilder->andWhere('e.name like ?2');
                $ticketQueryBuilder->setParameter(2, '%' . $this->postHash['eventName'] . '%');
            }

            // �`�P�b�g���
            if(!empty($this->postHash['name'])){
                $ticketQueryBuilder->andWhere('t.name like ?3');
                $ticketQueryBuilder->setParameter(3, '%' . $this->postHash['name'] . '%');
            }

            // �`�P�b�g���
            if(!empty($this->postHash['lowPrice']) && !empty($this->postHash['hiPrice'])){
                $ticketQueryBuilder->andWhere('t.price between ?4 AND ?5');
                $ticketQueryBuilder->setParameter(4, $this->postHash['lowPrice']);
                $ticketQueryBuilder->setParameter(5, $this->postHash['hiPrice']);
            }elseif(!empty($this->postHash['lowPrice'])){
                $ticketQueryBuilder->andWhere('t.price >= ?4');
                $ticketQueryBuilder->setParameter(4, $this->postHash['lowPrice']);
            }elseif(!empty($this->postHash['hiPrice'])){
                $ticketQueryBuilder->andWhere('t.price  <= ?4');
                $ticketQueryBuilder->setParameter(4, $this->postHash['hiPrice']);
            }

            if(!empty($this->postHash['saleAt'])){

                $ticketQueryBuilder->andWhere('t.ticketSaleStartAt <= ?6 AND t.ticketSaleEndAt >= ?6');
                $ticketQueryBuilder->setParameter(6, str_replace('/', '-', $this->paramHash['saleAt']));
            }

             // ���̔�
            if (!empty($this->postHash['unSaleTicket'])){
                $checkStatus[] = 't.ticketSaleStartAt > ?8';
                $ticketQueryBuilder->setParameter(8, $time->format('Y-m-d H:i:s'));
            }

            // �̔���
            if (!empty($this->postHash['saleTicket'])){
                $checkStatus[] = 't.ticketSaleStartAt <= ?9 ANd t.ticketSaleEndAt >= ?9';
                $ticketQueryBuilder->setParameter(9, $time->format('Y-m-d H:i:s'));
            }
            // �I��
            if (!empty($this->postHash['endSaleTicket'])){
                $checkStatus[] = 't.ticketSaleEndAt < ?10';
                $ticketQueryBuilder->setParameter(10, $time->format('Y-m-d H:i:s'));
            }

            // �`�P�b�g�X�e�[�^�X
            if (!empty($this->postHash['ticketStyle'])){

                if($this->postHash['ticketStyle'] == 1){

                    $ticketQueryBuilder->andWhere('t.price > 0');
                }elseif($this->postHash['ticketStyle'] == 2){

                    $ticketQueryBuilder->andWhere('t.price = 0');
                }
            }

            // �X�e�[�^�X
            if (!empty($this->postHash['status'])){

                if($this->postHash['status'] == 1){

                    $ticketQueryBuilder->andWhere('t.status = 1');
                }elseif($this->postHash['status'] == 2){

                    $ticketQueryBuilder->andWhere('t.status = 0');
                }
            }

            // �폜�X�e�[�^�X
            if (!empty($this->postHash['deleteFlag'])){

                if($this->postHash['deleteFlag'] == 1){

                    $ticketQueryBuilder->andWhere('t.deleteFlag = 1');
                }elseif($this->postHash['deleteFlag'] == 2){

                    $ticketQueryBuilder->andWhere('t.deleteFlag = 0');
                }
            }

            // �X�e�[�^�X������where�ɒǉ�
            if (!empty($checkStatus)){
                $ticketQueryBuilder->andWhere(implode(' OR ', $checkStatus));
            }

            // �Z�b�V�������̕ۑ�
            $backendTicketSearch = new Zend_Session_Namespace('backendTicketSearch');
            $backendTicketSearch->postHash =  $this->postHash;
        }

        $ticketQueryBuilder->orderBy('e.name', 'ASC');

        // �`�P�b�g�����擾����
        $tickets = $ticketQueryBuilder->getQuery()->getResult();

        // view�ւ̃Z�b�g(�`�P�b�g���)
        $this->view->tickets = $tickets;

        if(isset($this->paramHash['page'])){
            $currentPage = $this->paramHash['page'];
        }
        // paginate�̃Z�b�g
        $this->setPaginate($tickets ,10 ,$currentPage ,$getParam);

        // view�ւ̃Z�b�g
        $this->view->form = $form;
        $this->view->title = $this->_title[$this->actionName]['success'];
    }

    /**
     * �`�P�b�g�ҏW
     */
    function editAction(){
        $ticket = $this->_em->getRepository('Ticket')
        ->findOneBy(array('id' => $this->paramHash['ticketId'], 'deleteFlag' => '0'));
        
        if (empty($ticket)){
            $e =  new Exception('<br />�E�`�P�b�g��񂪌�����܂���');
            $this->_errorMessages['title'] = $this->_title[$this->actionName]['error'];
            $this->_errorMessages['main'][] = sprintf('<p>%s</p>', $e->getMessage());
            $this->view->title = $this->_title[$this->actionName]['error'];
            $this->view->assign('errorMessages', $this->_errorMessages);
            throw $e;
        }
        
        // �t�H�[�����Z�b�g����
        $form = $this->_createZendFormIni($this->actionName);
        
        // �R���r�j���ςōw�����ꂽ�����`�P�b�g�̂����A�����\���ԓ��Ŗ������̃`�P�b�g�̐����擾�B
        $nowDateTime = new DateTime();
        $ticketEventCount = $this->_em->getRepository('TicketEvent')->createQueryBuilder('te')
        ->select('count(sc)')
        ->leftJoin('te.ticket', 't')
        ->leftJoin('te.settlementEvent', 'se')
        ->leftJoin('se.settlementConvenience', 'sc')
        ->andWhere('t.id = ?1')
        
        ->andWhere('sc.paymentStatus IS NULL')
        ->andWhere('sc.expiryDate >= ?2')
        ->andWhere('te.deleteFlag >= ?3')
        ->setParameter(1, $ticket)
        ->setParameter(2, $nowDateTime)
        ->setParameter(3, 0)
        ->getQuery()
        ->getSingleScalarResult();
        
        // �w���σ`�P�b�g������ꍇ���`�P�b�g����ҏW�ł���悤�Ɏd�l�ύX
        
        // �w���σ`�P�b�g������ꍇ�A�ҏW�ł��Ȃ�����
        //if($ticket->getQuantity() != $ticket->getTicketStock()->getStock() or $ticketEventCount > 0){

          //  $this->view->editFlag = true;
            //return true;
        //}
        

        // POST�l���Z�b�g����Ă��āAvalidate�N���A
        if($this->request->isPost() && $form->isValidPartial($this->paramHash)){
            /**
             * �w�ʍL��URL�̃o���f�[�V���� added by matsui
             *
             * http://�܂���https://�Ŏn�܂�URL�Ŏn�܂镶����
             * �������͋󕶎��ł���΋��e����
             */
    
            $backAdUrl = $this->paramHash['backAdUrl'];
            if (!preg_match('/^https?:\/\/.+/i', $backAdUrl)) {
                if (empty($backAdUrl)) {
                    // �󕶎�orNULL�͋��e����
                    $backAdUrl = "";
                }
                else {
                    throw new Exception("�s����URL�ł��Bhttp://�܂���https://�Ŏn�܂�URL�̂ݓo�^�\�ł�");
                }
            }

            $startAt = new DateTime(sprintf(
                    '%s %02d:%02d:00',
                    str_replace('/', '-', $this->paramHash['ticketSaleStartAtYmd']),
                    $this->paramHash['ticketSaleStartAtHour'],
                    $this->paramHash['ticketSaleStartAtMinute']
            ));
            $endAt = new DateTime(sprintf(
                    '%s %02d:%02d:00',
                    str_replace('/', '-', $this->paramHash['ticketSaleEndAtYmd']),
                    $this->paramHash['ticketSaleEndAtHour'],
                    $this->paramHash['ticketSaleEndAtMinute']
            ));
			
            // �ۑ��f�[�^�̃Z�b�g
            $now = new \Datetime();
            $originalQuantity = $ticket->getQuantity();
            $originalStock = $ticket->getTicketStock()->getStock();
            $culcStock = $this->paramHash['quantity']-$originalQuantity+$originalStock;
            // �f�[�^�X�V
            $ticket->setName($this->paramHash['name']);
            $ticket->setPrice($this->paramHash['price']);
            $ticket->setTicketSaleStartAt($startAt);
            $ticket->setTicketSaleEndAt($endAt);
            $ticket->setStatus($this->paramHash['status']);
            $ticket->setHasMultipleImages($this->paramHash['hasMultipleImages']);
            $ticket->setDeleteFlag($this->paramHash['deleteFlag']);
            $ticket->setQuantity($this->paramHash['quantity']);
            $ticket->setBuyMaxLimit($this->paramHash['buyMaxLimit']);
            $ticket->setNoticeDescription($this->paramHash['noticeDescription']);
            $ticket->setBackAdUrl($backAdUrl);
            $ticket->setUpdatedAt($now);
            $ticket->setUpdatedBy($this->auth->uid);
            $ticketStock = $ticket->getTicketStock();
            $ticketStock->setStock($culcStock);

            // �f�U�C���`�P�b�g�摜�̓o�^
            $frontImageId = $this->paramHash['frontImageId'];
            if (!empty($frontImageId) && $frontImageId > 0) {
                $this->_saveImage($ticket, $frontImageId, '2', $this->auth->uid, false);
            }

            // �f�U�C���`�P�b�g�摜�̓o�^
            $backImageId = $this->paramHash['backImageId'];
            if (!empty($backImageId) && $backImageId > 0) {
                $this->_saveImage($ticket, $backImageId, '3', $this->auth->uid, false);
            }

            // �����摜�����b�N�摜�̓o�^
            $lockImageId = $this->paramHash['lockImageId'];
            if (!empty($lockImageId) && $lockImageId > 0) {
                $this->_saveImage($ticket, $lockImageId, '4', $this->auth->uid, false);
            }
			
            // ���ڂ̃f�[�^��
            $no = 0;
            foreach($this->paramHash as $name => $atom){
               if( !preg_match( '/backImageId/' ,$name )){ continue; }
               if( $name == 'backImageId'){ continue; }
               $title = str_replace('backImageId', 'backImageTitle', $name); 
               $prob  = str_replace('backImageId', 'backImageProb', $name); 
               $this->_saveCollectionImage($ticket, $this->paramHash [ $title ], $this->paramHash [ $prob ], $atom, $no, $this->auth->uid, false);
               $no++;
            }
            
            $this->_em->persist($ticket); // persist�����̂��܂��Ȃ�
            $this->_em->persist($ticketStock);
            $this->_em->flush(); // DB���f(���ۂ�update)
            
            // �������b�Z�[�W�̃Z�b�g
            $this->view->completeMessage = $this->viewMessages['complete']['edit'];
            // �摜�擾�p�X�V����
            $updatedAt = $ticket->getUpdatedAt();
            if (empty($updatedAt))
                $updatedAt = $ticket->getCreatedAt();
            $this->view->assign('ticketUpdatedAt', $updatedAt);
            
            
            
            // �\�摜���ݎ擾A
            /*
            $image = $this->_em->getRepository('TicketImages')->findOneBy(array(
                    'ticket' => $ticket->getId(),
                    'type'=> '2',
                    'deleteFlag' => '0'));
            $frontImageId = (empty($image))? '' : $image->getId();
            */
            $ti = $this->_em->getRepository('TicketImages');
            $image = $ti->findOneBy(array(
            		'ticket' => $ticket->getId(),
            		'type'=> '2',
            		'deleteFlag' => '0'));
            $frontImageId = (empty($image))? '' : $image->getId();
          
            // ���摜���ݎ擾
            /*
            $image = $this->_em->getRepository('TicketImages')->findOneBy(array(
                    'ticket' => $ticket->getId(),
                    'type'=> '3',
                    'deleteFlag' => '0'));
            $backImageId = (empty($image))? '' : $image->getId();
			*/
            $image = $ti->findOneBy(array(
            		'ticket' => $ticket->getId(),
            		'type'=> '3',
            		'deleteFlag' => '0'));
            $backImageId = (empty($image))? '' : $image->getId();

            // ���b�N�摜���ݎ擾
            /*
            $image = $this->_em->getRepository('TicketImages')->findOneBy(array(
                    'ticket' => $ticket->getId(),
                    'type'=> '4',
                    'deleteFlag' => '0'));
            $lockImageId = (empty($image))? '' : $image->getId();
			*/
            $image = $ti->findOneBy(array(
            		'ticket' => $ticket->getId(),
            		'type'=> '4',
            		'deleteFlag' => '0'));
            $lockImageId = (empty($image))? '' : $image->getId();

            // �R���N�V�����摜
            $ti = $this->_em->getRepository('TicketCollectionImage');
            $images = $ti->findOneBy(array(
            		'ticket' => $ticket->getId(),
            		));
            /*
            $images = $this->_em->getRepository('TicketCollectionImage')->findBy(array(
                    'ticket' => $ticket->getId(),
                    ));
			*/
            $backImageIds = array();
            foreach($images as $atom){
                array_push($backImageIds, $atom);
            }

            $this->view->assign('frontImageId', $frontImageId);
            $this->view->assign('backImageId', $backImageId);
            $this->view->assign('lockImageId', $lockImageId);
            $this->view->assign('backImageIds', $backImageIds);
            
            // �ꎞ�ۑ��摜�̎c�[���c���Ȃ����߃t�H�[�����C������
            $defaulutHash = $this->paramHash;
            $defaultHash['frontImageId'] = null;
            $defaultHash['backImageId'] = null;
            $form->setDefaults($defaultHash);
        }elseif($this->request->isGet()){
            // ����A�N�Z�X���Ƀ`�P�b�g��񂪂���Γǂݏo���ăf�t�H���g�l�Ƃ���
            $defaultHash = array();
            $defaultHash['name'] = $ticket->getName();
            $defaultHash['price'] = $ticket->getPrice();
            $defaultHash['ticketSaleStartAtYmd'] = $ticket->getTicketSaleStartAt()->format('Y/m/d');
            $defaultHash['ticketSaleStartAtHour'] = $ticket->getTicketSaleStartAt()->format('H');
            $defaultHash['ticketSaleStartAtMinute'] = $ticket->getTicketSaleStartAt()->format('i');
            $defaultHash['ticketSaleEndAtYmd'] = $ticket->getTicketSaleEndAt()->format('Y/m/d');
            $defaultHash['ticketSaleEndAtHour'] = $ticket->getTicketSaleEndAt()->format('H');
            $defaultHash['ticketSaleEndAtMinute'] = $ticket->getTicketSaleEndAt()->format('i');
            $defaultHash['status'] = $ticket->getStatus();
            $defaultHash['hasMultipleImages'] = $ticket->getHasMultipleImages();
            $defaultHash['deleteFlag'] = $ticket->getDeleteFlag();
            $defaultHash['quantity'] = $ticket->getQuantity();
            $defaultHash['buyMaxLimit'] = $ticket->getBuyMaxLimit();
            $defaultHash['noticeDescription'] = $ticket->getNoticeDescription();
            $backAdUrl = $ticket->getBackAdUrl();
            if (empty($backAdUrl)) {
                $backAdUrl = "";
            }
            $defaultHash['backAdUrl'] = $backAdUrl;
            
            // �f�t�H���g�l�̃Z�b�g
            $form->setDefaults($defaultHash);
            
            // �摜�擾�p�X�V����
            $updatedAt = $ticket->getUpdatedAt();
            if (empty($updatedAt))
                $updatedAt = $ticket->getCreatedAt();
            $this->view->assign('ticketUpdatedAt', $updatedAt);
            
            // �\�摜���ݎ擾
            /*
            $image = $this->_em->getRepository('TicketImages')->findOneBy(array(
                    'ticket' => $ticket->getId(),
                    'type'=> '2',
                    'deleteFlag' => '0'));
            $frontImageId = (empty($image))? '' : $image->getId();
			*/
            $ti = $this->_em->getRepository('TicketCollectionImage');
            $images = $ti->findOneBy(array(
            		'ticket' => $ticket->getId(),
            ));
            $backImageIds = array();
            foreach($images as $atom){
            	array_push($backImageIds, $atom);
            }
            
            
            /*
            // ���摜���ݎ擾
            $image = $this->_em->getRepository('TicketImages')->findOneBy(array(
                    'ticket' => $ticket->getId(),
                    'type'=> '3',
                    'deleteFlag' => '0'));
            $backImageId = (empty($image))? '' : $image->getId();
			*/
            $image = $ti->findOneBy(array(
            		'ticket' => $ticket->getId(),
            		'type'=> '3',
            		'deleteFlag' => '0'));
            /*
            // ���b�N�摜���ݎ擾
            $image = $this->_em->getRepository('TicketImages')->findOneBy(array(
                    'ticket' => $ticket->getId(),
                    'type'=> '4',
                    'deleteFlag' => '0'));
            $lockImageId = (empty($image))? '' : $image->getId();
			*/
            $image = $ti->findOneBy(array(
            		'ticket' => $ticket->getId(),
            		'type'=> '4',
            		'deleteFlag' => '0'));
            
            $images = $ti->findOneBy(array(
            		'ticket' => $ticket->getId(),
            ));
            $backImageIds = array();
            foreach($images as $atom){
            	array_push($backImageIds, $atom);
            }
			


            $backImageId = (empty($image))? '' : $image->getId();
            $this->view->assign('frontImageId', $frontImageId);
            $this->view->assign('backImageId', $backImageId);
            $this->view->assign('lockImageId', $lockImageId);
            $this->view->assign('backImageIds', $backImageIds);
        }
		
        // view�ւ̃Z�b�g
        $this->view->form = $form;
        $this->view->ticketId = $this->paramHash['ticketId'];
        $this->view->title = $this->_title[$this->actionName]['success'];
    }


    /**
     * �`�P�b�g���ڍ�
     */
    function detailAction(){

        $ticket = $this->_em->getRepository('Ticket')->findOneBy(array('id' => $this->paramHash['ticketId']));

        if (empty($ticket)){
            $e =  new Exception('<br />�E�`�P�b�g��񂪌�����܂���');
            $this->_errorMessages['title'] = $this->_title[$this->actionName]['error'];
            $this->_errorMessages['main'][] = sprintf('<p>%s</p>', $e->getMessage());
            $this->view->title = $this->_title[$this->actionName]['error'];
            $this->view->assign('errorMessages', $this->_errorMessages);
            throw $e;
        }

        $this->view->ticket = $ticket;
        $this->view->title = $this->_title[$this->actionName]['success'];
        // �摜�擾�p�X�V����
        $updatedAt = $ticket->getUpdatedAt();
        if (empty($updatedAt))
            $updatedAt = $ticket->getCreatedAt();
        $this->view->assign('ticketUpdatedAt', $updatedAt);
        $ti = $this->_em->getRepository('TicketImages');
        $image = $ti->findOneBy(array('ticket' => $ticket->getId(), 'type'=> '2', 'deleteFlag' => '0'));
        $frontImageId = (empty($image))? null : $image->getId();
        
        $image = $ti->findOneBy(array('ticket' => $ticket->getId(), 'type'=> '3', 'deleteFlag' => '0'));
        $backImageId = (empty($image))? null : $image->getId();

        $image = $ti->findOneBy(array('ticket' => $ticket->getId(), 'type'=> '4', 'deleteFlag' => '0'));
        $lockImageId = (empty($image))? null : $image->getId();
        
        $this->view->assign('frontImageId', $frontImageId);
        $this->view->assign('backImageId', $backImageId);
        $this->view->assign('lockImageId', $lockImageId);
    }

    /**
     * �`�P�b�g�摜��
     */
    
    public function imageAction() {
        try{
            //$this->_helper->layout->disableLayout();
            $front = Zend_Controller_Front::getInstance();
            $front->getPlugin('Zend_Layout_Controller_Plugin_Layout')->getLayout()->disableLayout();
            $this->_helper->removeHelper('viewRenderer');
            
            $req = $this->getRequest();
            $params = $req->getParams();
            
           // �C�x���gid�̎擾
            $id = $params['ticketId'];
            if(empty($id))
                throw new Exception('�`�P�b�g�̓��肪�o���܂���B');
            
            $type = $req->getParam('type');
            // 1: ���C���摜(���g�p)
            // 2: ������O�摜
            // 3: �������摜 
            if ($type != 1 && $type != 2 && $type != 3)
                throw new Exception('�摜�^�C�v�̓��肪�ł��܂���B');
                        
            $width = $req->getParam('width');
            if(empty($width) || $width > 640)
                throw new Exception('�摜�T�C�Y�̓��肪�o���܂���B'); // ���t�@�N�^�����O-���C���摜�ő�T�C�Y�Ńu���b�N
            
            // �摜�擾
            if($type == 2){
                $image = $this->_em->getRepository('TicketImages')
                ->findOneBy(array('ticket' => $id,'type'=> $type, 'deleteFlag' => '0'));
            }elseif(!empty($params['collectionId'])){
     	        $image = $this->_em->getRepository('TicketCollectionImage')
 	        ->findOneBy(array('id' => $params['collectionId'] ));
            }else{
                $image = $this->_em->getRepository('TicketImages')
                ->findOneBy(array('ticket' => $id,'type'=> $type, 'deleteFlag' => '0'));
            }
            
            // �摜�擾
            $image = $this->_em->getRepository('TicketImages')
            ->findOneBy(array('ticket' => $id,'type'=> $type, 'deleteFlag' => '0'));
            
            if(!empty($image)){
                $blob = $image->getData();
                $editor = new Tixee_Image_Editor($blob, Tixee_Image_Editor::BLOB);
                $editor->resizeWidth($width);
                $imageData = $editor->getImageBlob();
            }
            else {
                // �摜��������Ȃ��ꍇ�͉摜�ǂ����邩�v����
                   
//                     $defaultImagePath;
//                     switch($type){
//                         case '1':
//                             // ���C���摜
//                             $defaultImagePath = APPLICATION_PATH. "/configs/image/material/event/$type".".png";
//                             break;
//                         default:
//                             throw new Exception("�s���ȉ摜�^�C�v�̃��N�G�X�g�ł��B");
//                     }
//                     $editor = new Tixee_Image_Editor($defaultImagePath);
//                     $editor->resizeWidth($width);
//                     $imageData = $editor->getImageBlob();
            }
            
            // ���X�|���X�I�u�W�F�N�g�̐ݒ�
            $this->getResponse()
            ->setHeader('Content-Type', 'image/png')
            ->setHeader('Content-Length', strlen($imageData))
            ->setHeader('Last-Modified', gmdate('D, d M Y H:i:s').' GMT')
            ->appendBody($imageData);
        } catch (Exception $e) {
            
            // ���t�@�N�^�����O-��ʂɃA�E�g�v�b�g�͂��Ȃ��̂ŁA�K�؂ȃG���[�����̃��M���O
            $this->_errorMessages['title'] = $this->_title[$req->getActionName()]['error'];
            $this->_errorMessages['main'][] = sprintf('<p>%s</p>', $e->getMessage());
            $this->view->assign('errorMessages', $this->_errorMessages);
            throw $e;
        }
    }


   
    
    	
    	
    /**
     * �`�P�b�g�摜�o�^
     */
    
    public function tmpUploadImageAction() {
        $imageId = 0;
        $type = 0;
        $src = "";
        $retval = $this->_codeConf->failed;
      
        try {
            $front = Zend_Controller_Front::getInstance();
            $front->getPlugin('Zend_Layout_Controller_Plugin_Layout')->getLayout()->disableLayout();
          
            // �p�����[�^�擾
            $req = $this->getRequest();
            $params = $req->getParams();
            $type = $params["type"];
            $lastImageId = null;


            switch ($type) {
                case 2:
                    $lastImageId = $params["frontImageId"];
                    break;
                    /*
                case 3:
                    foreach($params as $key => $val){
                       if( preg_match('/backImageId/', $key)){
                          $lastImageId = $val; 
                          break;
                       }
                    }
                    break;
*/
                case 4:
                  	$lastImageId = $params["lockImageId"];
                   	break;
                default:
                    break;
            }

            if(empty($this->auth->uid))
                throw new Exception('���[�U�[��������܂���B', $this->_codeConf->invalid_access);
            
            // �t�@�C���̎擾
            $upload = new Zend_File_Transfer();
            $files = $upload->getFileInfo();
            
            // �A�b�v�\�ȃt�@�C����1��
            if(1 != count($files))

                throw new Exception('�t�@�C���̃A�b�v���[�h�Ɏ��s���܂����B', $this->_codeConf->invalid_access);
            
            $tempImage = null;
            foreach($files as $file => $info){
                // ��ʂ���A�b�v���[�h���ꂽ�t�@�C���ȊO�͋����Ȃ��B
                if(!$upload->isUploaded($file))
                    throw new Exception('�s���ȃA�b�v���[�h�s�ׂł��B', $this->_codeConf->invalid_access);
                
                // �o���f�[�V����
                $editor = new Tixee_Image_Editor($info['tmp_name']);
                if(!$editor->valid())
                    throw new Exception('�摜�t�@�C���ł͂���܂���B', $this->_codeConf->validate->failed);
                
                // �t�@�C�����擾
                $imageName = $info['name'];
 
               // ���t�@�N�^�����O-�`�P�b�g�摜�ő�T�C�Y
                $editor->resizeSquare(640, 600);
                $imageBlob = $editor->getImageBlob();
                
                // ���f���̍쐬
                $tempImage = new \TempImages();
                // �{�o�^���ɂ͎g�p���Ȃ����߈ꎞ�ۑ��ł��ۑ�����K�v�Ȃ��������A�J����������̂ňꉞ����Ă���
                $tempImage->setFileName($imageName);
                $tempImage->setData($imageBlob);
                $tempImage->setDeleteFlag(false);
                $tempImage->setCreatedAt(new \Datetime());
                $tempImage->setCreatedBy($this->auth->uid);
              
                // DB�ۑ��Ɣ��f
                $this->_em->persist($tempImage);
                $this->_em->flush();
                
                $imageId = $tempImage->getId();
            }
          
            // �����ꎞ�ۑ��摜�����݂���ꍇ�͍폜���Ă���
            if (!empty($lastImageId) && $lastImageId > 0) {
                $this->_em->getRepository("TempImages")->deleteTempImage($lastImageId);
            }
            $this->_em->clear();
            
            $src = $this->view->baseUrl("backend/ticket/load-image/imageid/{$imageId}/w/300");
            $retval = $this->_codeConf->success;
        
        } catch (Exception $e) {
            $current = date('Y-m-d H:i:s');
            $logPath = '/tmp/tmpUploadImage.log';
            file_put_contents($logPath, "[{$current}] EventController catch\n", FILE_APPEND);
            file_put_contents($logPath, "[{$current}] {$e->getMessage()}\n", FILE_APPEND);
            
            $retval = $e->getCode();
        }
        
        $data = array(
                'result' => $retval,
                'src' => $src,
                'type' => $type,
                'image_id' => $imageId,
        );
        $this->view->data = json_encode($data);
    }
    
    
    /*
    public function tmpUploadImageAction() {
    	$imageId = 0;
    	$type = 0;
    	$src = "";
    	$retval = $this->_codeConf->failed;
    
    	try {
    		$front = Zend_Controller_Front::getInstance();
    		$front->getPlugin('Zend_Layout_Controller_Plugin_Layout')->getLayout()->disableLayout();
    
    		// �p�����[�^�擾
    		$req = $this->getRequest();
    		$params = $req->getParams();
    		$type = $params["type"];
    		$lastImageId = null;
    		switch ($type) {
    			case 2:
    				$lastImageId = $params["frontImageId"];
    				break;
    			case 3:
    				$lastImageId = $params["backImageId"];
    				break;
    			default:
    				break;
    		}
    
    		if(empty($this->auth->uid))
    			throw new Exception('���[�U�[��������܂���B', $this->_codeConf->invalid_access);
    
    		// �t�@�C���̎擾
    		$upload = new Zend_File_Transfer();
    		$files = $upload->getFileInfo();
    
    		// �A�b�v�\�ȃt�@�C����1��
    		if(1 != count($files))
    			throw new Exception('�t�@�C���̃A�b�v���[�h�Ɏ��s���܂����B', $this->_codeConf->invalid_access);
    
    		$tempImage = null;
    		foreach($files as $file => $info){
    			// ��ʂ���A�b�v���[�h���ꂽ�t�@�C���ȊO�͋����Ȃ��B
    			if(!$upload->isUploaded($file))
    				throw new Exception('�s���ȃA�b�v���[�h�s�ׂł��B', $this->_codeConf->invalid_access);
    
    			// �o���f�[�V����
    			$editor = new Tixee_Image_Editor($info['tmp_name']);
    			if(!$editor->valid())
    				throw new Exception('�摜�t�@�C���ł͂���܂���B', $this->_codeConf->validate->failed);
    
    			// �t�@�C�����擾
    			$imageName = $info['name'];
    			// ���t�@�N�^�����O-�`�P�b�g�摜�ő�T�C�Y
    			$editor->resizeSquare(640, 600);
    			$imageBlob = $editor->getImageBlob();
    
    			// ���f���̍쐬
    			$tempImage = new \TempImages();
    			// �{�o�^���ɂ͎g�p���Ȃ����߈ꎞ�ۑ��ł��ۑ�����K�v�Ȃ��������A�J����������̂ňꉞ����Ă���
    			$tempImage->setFileName($imageName);
    			$tempImage->setData($imageBlob);
    			$tempImage->setDeleteFlag(false);
    			$tempImage->setCreatedAt(new \Datetime());
    			$tempImage->setCreatedBy($this->auth->uid);
    
    			// DB�ۑ��Ɣ��f
    			$this->_em->persist($tempImage);
    			$this->_em->flush();
    
    			$imageId = $tempImage->getId();
    		}
    
    		// �����ꎞ�ۑ��摜�����݂���ꍇ�͍폜���Ă���
    		if (!empty($lastImageId) && $lastImageId > 0) {
    			$this->_em->getRepository("TempImages")->deleteTempImage($lastImageId);
    		}
    		$this->_em->clear();
    
    		$src = $this->view->baseUrl("backend/ticket/load-image/imageid/{$imageId}/w/300");
    		$retval = $this->_codeConf->success;
    
    	} catch (Exception $e) {
    		$current = date('Y-m-d H:i:s');
    		$logPath = '/tmp/tmpUploadImage.log';
    		file_put_contents($logPath, "[{$current}] EventController catch\n", FILE_APPEND);
    		file_put_contents($logPath, "[{$current}] {$e->getMessage()}\n", FILE_APPEND);
    
    		$retval = $e->getCode();
    	}
    
    	$data = array(
    			'result' => $retval,
    			'src' => $src,
    			'type' => $type,
    			'image_id' => $imageId,
    	);
    	$this->view->data = json_encode($data);
    }
    */
    
    /**
     * �ꎞ�ۑ��摜��
     * 
     * @throws Exception
     */
    public function loadImageAction() {
        try{
            $front = Zend_Controller_Front::getInstance();
            $front->getPlugin('Zend_Layout_Controller_Plugin_Layout')->getLayout()->disableLayout();
            $this->_helper->removeHelper('viewRenderer');

            $req = $this->getRequest();
            $params = $req->getParams();
            // �C�x���gid�̎擾
            $id = $params['imageid'];
            $width = $params['w'];
            
            if(empty($id))
                throw new Exception('�C�x���g�̓��肪�o���܂���B');
            
            if(empty($width) || $width > 640)
                throw new Exception('�摜�T�C�Y�̓��肪�o���܂���B'); // ���t�@�N�^�����O-���C���摜�ő�T�C�Y�Ńu���b�N
            
            $image = $this->_em->getRepository('TempImages')->getTempImage($id);
            if (empty($image))
                throw new Exception('image not found');
            
            $blob = $image->getData();
            $editor = new Tixee_Image_Editor($blob, Tixee_Image_Editor::BLOB);
            $editor->resizeWidth($width);
            $imageData = $editor->getImageBlob();
            
            // ���X�|���X�I�u�W�F�N�g�̐ݒ�
            $this->getResponse()
            ->setHeader('Content-Type', 'image/png')
            ->setHeader('Content-Length', strlen($imageData))
            ->setHeader('Last-Modified', gmdate('D, d M Y H:i:s').' GMT')
            ->appendBody($imageData);
        }catch (Exception $e){
            // ���t�@�N�^�����O-��ʂɃA�E�g�v�b�g�͂��Ȃ��̂ŁA�K�؂ȃG���[�����̃��M���O
            $this->_errorMessages['title'] = $this->_title[$req->getActionName()]['error'];
            $this->_errorMessages['main'][] = sprintf('<p>%s</p>', $e->getMessage());
            $this->view->assign('errorMessages', $this->_errorMessages);
            throw $e;
        }
    }
    
    /**
     *  �摜�̖{�o�^�ƈꎞ�ۑ��摜�̍폜
     *  
     *  @param \Ticket $ticket �`�P�b�g
     *  @param \TempImages tmpImage �摜�ꎞ�ۑ�
     *  @param int type �摜��� 1:���C�� 2:������O 3:�������
     *  @param bigint $uid
     *  @param bool isCommit DB�ɏ������ޏꍇ��true�A����ȊO��false
     */
    private function _saveImage($ticket, $tmpImageId, $type, $uid, $isCommit = true) {
        $ti = $this->_em->getRepository('TempImages');
        $tmpImage = $ti->getTempImage($tmpImageId, true);
        
        if (empty($tmpImage))
            throw new \Exception('�A�b�v���[�h�摜��������܂���B');
        
        $image = $this->_em->getRepository('TicketImages')
        ->findOneBy(array('ticket' => $ticket->getId(), 'deleteFlag' => '0', 'type' => $type));
        if (empty($image)) {
            // �V�K�Ή�  
            $image = new \TicketImages();
            $image->setType($type);
            $image->setGdImagetype(0); // �g���Ă��Ȃ�
            $image->setX(0); // �g���Ă��Ȃ�
            $image->setY(0); // �g���Ă��Ȃ�
            $image->setStatus(1); // �g���Ă��Ȃ�
            $image->setDeleteFlag(0);
            $image->setCreatedAt(new \Datetime());
            $image->setCreatedBy($uid);
            $createdByPlatform = $this->_em->getRepository('CreatedByPlatform')->findOneBy(array('name' => 'pc'));
            $image->setCreatedByPlatform($createdByPlatform);
            $image->setTicket($ticket);
        }
        else {
            $image->setUpdatedAt(new \Datetime());
            $image->setUpdatedBy($uid);
        }
        $image->setData($tmpImage->getData());
        
        $this->_em->persist($image);
        $ti->deleteTempImage($tmpImageId);
        if ($isCommit)
            $this->_em->flush();
    }
	
    /**
     *  �摜�̖{�o�^�ƈꎞ�ۑ��摜�̍폜
     *
     *  @param \Ticket $ticket �`�P�b�g
     *  @param string title �摜�^�C�g��
     *  @param string prob Probability
     *  @param \TempImages tmpImage �摜�ꎞ�ۑ�
     *  @param int type �摜��� 1:���C�� 2:������O 3:�������
     *  @param bigint $uid
     *  @param bool isCommit DB�ɏ������ޏꍇ��true�A����ȊO��false
     */
    private function _saveCollectionImage($ticket, $title, $prob, $tmpImageId, $no, $uid, $isCommit = true) {
    	// �m���A�摜�^�C�g���f�[�^�̂ǂ��炩���󂾂����ꍇ�������Ȃ�
    	if( empty($title) or empty($prob) ){ return; }
    
    	$image = $this->_em->getRepository('TicketCollectionImage')->findBy(array('ticket' => $ticket->getId()));
    	// �o�^�C���[�W�����A���������C���[�W����łȂ��ꍇ
    	if (count($image) < $no + 1 and !empty($tmpImageId)) {
    		// �V�K�Ή�
    		$image = new \TicketCollectionImage();
    		$image->setGdImagetype(0); // �g���Ă��Ȃ�
    	}else{
    		foreach($image as $image_no => $atom){
    			if($image_no == $no){
    				$image = $atom;
    				break;
    			}
    		}
    	}
    
    	$image->setTicket($ticket);
    	$image->setTitle($title);
    	$image->setProbability($prob);
    	if(!empty($tmpImageId)){
    		$tmpImage = $this->_em->getRepository('TempImages')->getTempImage($tmpImageId, true);
    		$image->setData($tmpImage->getData());
    		$this->_em->getRepository('TempImages')->deleteTempImage($tmpImageId);
    	}
    
    	$this->_em->persist($image);
    	if ($isCommit)
    		$this->_em->flush();
    }
    /**
     * tsv�A�N�V����
     *
     * �`�P�b�g�֘A�̃f�[�^��TSV�C���|�[�g�����ʂ��o�͂���A�N�V�����B
     * �ȑ��f�[�^�̎�荞�݂ɑΉ�����B
     *
     * @access public
     */
    public function tsvAction() {
    	// �t�H�[������
    	$aname =& $this->actionName;
    	$formSeatAttr = $this->_createZendFormIni("{$aname}-seatattribute");
    
    	// form�ɃC�x���g�Z�b�gID��n��
    	$ticketId = $this->paramHash['ticketId'];
    	$formSeatAttr->getElement('ticketId')->setValue($ticketId);
    
    	// �e���v���ϐ��Z�b�g
    	$this->view->formSeatAttribute = $formSeatAttr;
    
    	$this->view->pgurl = $this->_createControllerUrl('tsv-progress', '', '');
    	$this->view->title = $this->_title[$aname]['success'];
    }
    /**
     * tsv-event�A�N�V����
     *
     * �C�x���g�Z�b�g�֘A�̃C�x���g�f�[�^��TSV�C���|�[�g����A�N�V�����B
     *
     * @access public
     */
    public function tsvSeatattributeAction() {
    	$this->_saveTsvData('seatAttribute', '�ȑ����');
    }
    
    /**
     * TSV�t�@�C����DB�ۑ�
     *
     * TSV�t�@�C�����f�[�^�ɓW�J����DB�֊i�[����B
     *
     * @access private
     *
     * @param string $type  TSV�����^�C�v
     * @param string $title �����������̃^�C�g���w��
     */
    private function _saveTsvData($type, $title) {
    	try {
    		// �p�����[�^�擾
    		$form = $this->_createZendFormIni($this->actionName);
    
    		// CSV�A�b�v���[�h����
    		if (!$this->getRequest()->isPost()) {
    			throw new Exception('Unsupported HTTP Method');
    		}
    		if (!$form->isValid($this->paramHash)) {
    			$msgI = implode('<br />', $form->getElement('ticketId')->getMessages());
    			$msgF = implode('<br />', $form->getElement('tsv')->getMessages());
    
    			$msg = ($msgI && $msgF) ? '<br />' : '';
    			$msg = "{$msgI}{$msg}{$msgF}";
    			throw new Exception($msg);
    		}
    
    		// TSV�`�F�b�N
    		$tsvAdapter = $form->getElement('tsv')->getTransferAdapter();
    		$tsvInfo    = $tsvAdapter->getFileInfo('tsv');
    		$tsvInfo    = $tsvInfo['tsv'];
    		$tsvWorker  = new Tixee_Tsv_Import_SeatAttribute($this->_em);
    
    		if (!$tsvWorker->isValidTsvType($tsvInfo['type'])) {
    			throw new Exception("���p�ł��Ȃ��t�@�C���^�C�v�ł��i{$tsvInfo['type']}�j");
    		}
    
    		// �K�v�ȏ���DB�擾
    		$em =& $this->_em;
    
    		$ticket  = $em->getRepository('Ticket')->findOneBy(array('id' => $this->paramHash['ticketId']));
    		if (empty($ticket)) {
    			throw new Exception('�`�P�b�g��������܂���B');
    		}
    
    		$user = $em->getRepository('User')->findOneBy(array('id' => $this->auth->uid));
    		if (empty($user)) {
    			throw new Exception('�o�^�ҁi���O�C�����j�̃��[�U�[��񂪌�����܂���B');
    		}
    
    		// �C�x���g�f�[�^�o�^
    		$method = 'import' . ucfirst(strtolower($type));
    		if (!method_exists($tsvWorker, $method)) {
    			throw new Exception('�T�|�[�g����Ă��Ȃ������^�C�v�ł��B');
    		}
    
    		$ret = $tsvWorker->$method($ticket, $user, $tsvInfo['tmp_name']);
    		@unlink($tsvInfo['tmp_name']); // �S�~�|��
    
    		// ���ʏo��
    		$msg = sprintf(
    				'%1$s�o�^�����F %1$s����=%2$d, �o�^=%3$d, ���s=%4$d',
    				($title != '') ? $title : '�f�[�^',
    				$ret['total'],
    				$ret['success'],
    				$ret['total'] - $ret['success']
    		);
    		if(!empty($ret['error'])) {
    			$msg .= "<br />";
    			foreach ($ret['error'] as $error) {
    				$msg .= "<b>{$error['line']}�s�� : {$error['error']}</b><br />";
    			}
    		}
    	}
    	catch (Exception $e) {
    		$msg = 'CSV�o�^�Ɏ��s�F ' . $e->getMessage();
    	}
    
    	// �����_�����O��~
    	$this->_helper->removeHelper('viewRenderer');
    	$this->_helper->layout->disableLayout();
    
    	// �A�b�v���[�h���ʂ�����
    	$res = $this->getResponse();
    	$res->setHeader('Content-Type', 'text/plain');
    	$res->setHeader('Content-Length', strlen($msg));
    	$res->setBody($msg);
    
    	$res->sendResponse();
    	exit();
    }
    
    /**
     * seatattr�A�N�V����
     *
     * ���ȏ��̃t�H�[�}�b�g��ݒ肷��A�N�V�����B
     *
     * @access public
     */
    public function seatFormatAction() {
    	$ticketId = $this->paramHash['ticketId'];
    
    	$ticket = $this->_em->getRepository('Ticket')
    	->findOneBy(array('id' => $ticketId, 'deleteFlag' => '0'));
    	if (empty($ticket)){
    		$e =  new Exception('<br />�E�`�P�b�g��񂪌�����܂���');
    		$this->_errorMessages['title'] = $this->_title[$this->actionName]['error'];
    		$this->_errorMessages['main'][] = sprintf('<p>%s</p>', $e->getMessage());
    		$this->view->title = $this->_title[$this->actionName]['error'];
    		$this->view->assign('errorMessages', $this->_errorMessages);
    		throw $e;
    	}
    
    	// �t�H�[������
    	$form = $this->_createZendFormIni($this->actionName);
    
    	// �e���v���ϐ��Z�b�g
    	$this->view->formSeatAttribute = $form;
    
    	if($this->request->isPost() && $form->isValidPartial($this->paramHash)) {
    		// �ۑ��f�[�^�̃Z�b�g
    		$now = new \Datetime();
    		// �f�[�^�X�V
    		$ticket->setSeatAttrRegexp($this->paramHash['seatFormat']);
    		$ticket->setUpdatedAt($now);
    		$ticket->setUpdatedBy($this->auth->uid);
    
    		$this->_em->persist($ticket); // persist�����̂��܂��Ȃ�
    		$this->_em->flush(); // DB���f(���ۂ�update)
    
    		// �������b�Z�[�W�̃Z�b�g
    		$this->view->completeMessage = $this->viewMessages['complete']['edit'];
    	}
    	elseif($this->request->isGet()) {
    		// ����A�N�Z�X���Ƀ`�P�b�g��񂪂���Γǂݏo���ăf�t�H���g�l�Ƃ���
    		$defaultHash = array();
    		$seatFormat = $ticket->getSeatAttrRegexp();
    		if (empty($seatFormat)) {
    			$seatFormat = "";
    		}
    		$defaultHash['seatFormat'] = $seatFormat;
    
    		// �f�t�H���g�l�̃Z�b�g
    		$form->setDefaults($defaultHash);
    
    	}
    
    	// view�ւ̃Z�b�g
    	$this->view->form = $form;
    	$this->view->ticketId = $this->paramHash['ticketId'];
    	$this->view->title = $this->_title[$this->actionName]['success'];
    }
 
}

