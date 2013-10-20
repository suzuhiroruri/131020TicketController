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
     * 初期化
     */
    public function init()
    {
       // Base継承
       parent::init();
       // 画像用
       $contextSwitch = $this->_helper->getHelper('contextSwitch');
       $contextSwitch->addActionContext('tmp-upload-image', 'xml')->initContext('xml');
    }

    /**
     * アクション前処理
     *
     * アクション実行前に呼び出される。
     *
     * @access public
     */
    public function preDispatch() {

        // Base継承
        parent::preDispatch();

        if(isset($this->paramHash['userId'])){
                // サブメニューのセット
                $this->view->submenu = 'user-submenu.phtml';
        }
        elseif(isset($this->paramHash['ticketId'])) {
        	$this->view->submenu = 'ticket-submenu.phtml';
        }
    }

     /**
     * チケット情報検索
     */
    public function indexAction()
    {
        // チケット情報初期化
        $tickets = array();

        $currentPage = '';

        $getParam = '';

        // フォームをセットする
        $form = $this->_createZendFormIni($this->request->getActionName());

        $ticketQueryBuilder = $this->_em->getRepository('Ticket')->createQueryBuilder('t');
        $ticketQueryBuilder->leftjoin('t.event' ,'e');

        // セッションの取得
        $backendTicketSearch = new Zend_Session_Namespace('backendTicketSearch');

        if(isset($backendTicketSearch->postHash) && $this->request->isGet()){

            if(isset($this->paramHash['s']) && $this->paramHash['s'] == 'clear'){

                // セッション破棄
                unset($backendTicketSearch->postHash);
            }else{
                // セッション情報の取得
                $this->postHash = $backendTicketSearch->postHash;
            }
        }

        // POST
        if(!empty($this->postHash) and $form->isValid($this->postHash)){

            // ステータス検索条件
            $checkStatus = array();

            // 現在時刻
            $time = new Datetime();

            // チケットID
            if(!empty($this->postHash['id'])){
                $ticketQueryBuilder->andWhere('t.id = ?1');
                $ticketQueryBuilder->setParameter(1, $this->postHash['id']);
            }

            // イベントID
            if(!empty($this->postHash['eventName'])){
                $ticketQueryBuilder->andWhere('e.name like ?2');
                $ticketQueryBuilder->setParameter(2, '%' . $this->postHash['eventName'] . '%');
            }

            // チケット種別
            if(!empty($this->postHash['name'])){
                $ticketQueryBuilder->andWhere('t.name like ?3');
                $ticketQueryBuilder->setParameter(3, '%' . $this->postHash['name'] . '%');
            }

            // チケット種別
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

             // 未販売
            if (!empty($this->postHash['unSaleTicket'])){
                $checkStatus[] = 't.ticketSaleStartAt > ?8';
                $ticketQueryBuilder->setParameter(8, $time->format('Y-m-d H:i:s'));
            }

            // 販売中
            if (!empty($this->postHash['saleTicket'])){
                $checkStatus[] = 't.ticketSaleStartAt <= ?9 ANd t.ticketSaleEndAt >= ?9';
                $ticketQueryBuilder->setParameter(9, $time->format('Y-m-d H:i:s'));
            }
            // 終了
            if (!empty($this->postHash['endSaleTicket'])){
                $checkStatus[] = 't.ticketSaleEndAt < ?10';
                $ticketQueryBuilder->setParameter(10, $time->format('Y-m-d H:i:s'));
            }

            // チケットステータス
            if (!empty($this->postHash['ticketStyle'])){

                if($this->postHash['ticketStyle'] == 1){

                    $ticketQueryBuilder->andWhere('t.price > 0');
                }elseif($this->postHash['ticketStyle'] == 2){

                    $ticketQueryBuilder->andWhere('t.price = 0');
                }
            }

            // ステータス
            if (!empty($this->postHash['status'])){

                if($this->postHash['status'] == 1){

                    $ticketQueryBuilder->andWhere('t.status = 1');
                }elseif($this->postHash['status'] == 2){

                    $ticketQueryBuilder->andWhere('t.status = 0');
                }
            }

            // 削除ステータス
            if (!empty($this->postHash['deleteFlag'])){

                if($this->postHash['deleteFlag'] == 1){

                    $ticketQueryBuilder->andWhere('t.deleteFlag = 1');
                }elseif($this->postHash['deleteFlag'] == 2){

                    $ticketQueryBuilder->andWhere('t.deleteFlag = 0');
                }
            }

            // ステータス条件をwhereに追加
            if (!empty($checkStatus)){
                $ticketQueryBuilder->andWhere(implode(' OR ', $checkStatus));
            }

            // セッション情報の保存
            $backendTicketSearch = new Zend_Session_Namespace('backendTicketSearch');
            $backendTicketSearch->postHash =  $this->postHash;
        }

        $ticketQueryBuilder->orderBy('e.name', 'ASC');

        // チケット情報を取得する
        $tickets = $ticketQueryBuilder->getQuery()->getResult();

        // viewへのセット(チケット情報)
        $this->view->tickets = $tickets;

        if(isset($this->paramHash['page'])){
            $currentPage = $this->paramHash['page'];
        }
        // paginateのセット
        $this->setPaginate($tickets ,10 ,$currentPage ,$getParam);

        // viewへのセット
        $this->view->form = $form;
        $this->view->title = $this->_title[$this->actionName]['success'];
    }

    /**
     * チケット編集
     */
    function editAction(){
        $ticket = $this->_em->getRepository('Ticket')
        ->findOneBy(array('id' => $this->paramHash['ticketId'], 'deleteFlag' => '0'));
        
        if (empty($ticket)){
            $e =  new Exception('<br />・チケット情報が見つかりません');
            $this->_errorMessages['title'] = $this->_title[$this->actionName]['error'];
            $this->_errorMessages['main'][] = sprintf('<p>%s</p>', $e->getMessage());
            $this->view->title = $this->_title[$this->actionName]['error'];
            $this->view->assign('errorMessages', $this->_errorMessages);
            throw $e;
        }
        
        // フォームをセットする
        $form = $this->_createZendFormIni($this->actionName);
        
        // コンビニ決済で購入された既存チケットのうち、入金可能期間内で未入金のチケットの数を取得。
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
        
        // 購入済チケットがある場合もチケット情報を編集できるように仕様変更
        
        // 購入済チケットがある場合、編集できなくする
        //if($ticket->getQuantity() != $ticket->getTicketStock()->getStock() or $ticketEventCount > 0){

          //  $this->view->editFlag = true;
            //return true;
        //}
        

        // POST値がセットされていて、validateクリア
        if($this->request->isPost() && $form->isValidPartial($this->paramHash)){
            /**
             * 背面広告URLのバリデーション added by matsui
             *
             * http://またはhttps://で始まるURLで始まる文字列
             * もしくは空文字であれば許容する
             */
    
            $backAdUrl = $this->paramHash['backAdUrl'];
            if (!preg_match('/^https?:\/\/.+/i', $backAdUrl)) {
                if (empty($backAdUrl)) {
                    // 空文字orNULLは許容する
                    $backAdUrl = "";
                }
                else {
                    throw new Exception("不正なURLです。http://またはhttps://で始まるURLのみ登録可能です");
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
			
            // 保存データのセット
            $now = new \Datetime();
            $originalQuantity = $ticket->getQuantity();
            $originalStock = $ticket->getTicketStock()->getStock();
            $culcStock = $this->paramHash['quantity']-$originalQuantity+$originalStock;
            // データ更新
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

            // デザインチケット画像の登録
            $frontImageId = $this->paramHash['frontImageId'];
            if (!empty($frontImageId) && $frontImageId > 0) {
                $this->_saveImage($ticket, $frontImageId, '2', $this->auth->uid, false);
            }

            // デザインチケット画像の登録
            $backImageId = $this->paramHash['backImageId'];
            if (!empty($backImageId) && $backImageId > 0) {
                $this->_saveImage($ticket, $backImageId, '3', $this->auth->uid, false);
            }

            // 複数画像時ロック画像の登録
            $lockImageId = $this->paramHash['lockImageId'];
            if (!empty($lockImageId) && $lockImageId > 0) {
                $this->_saveImage($ticket, $lockImageId, '4', $this->auth->uid, false);
            }
			
            // 何個目のデータか
            $no = 0;
            foreach($this->paramHash as $name => $atom){
               if( !preg_match( '/backImageId/' ,$name )){ continue; }
               if( $name == 'backImageId'){ continue; }
               $title = str_replace('backImageId', 'backImageTitle', $name); 
               $prob  = str_replace('backImageId', 'backImageProb', $name); 
               $this->_saveCollectionImage($ticket, $this->paramHash [ $title ], $this->paramHash [ $prob ], $atom, $no, $this->auth->uid, false);
               $no++;
            }
            
            $this->_em->persist($ticket); // persist処理のおまじない
            $this->_em->persist($ticketStock);
            $this->_em->flush(); // DB反映(実際のupdate)
            
            // 完了メッセージのセット
            $this->view->completeMessage = $this->viewMessages['complete']['edit'];
            // 画像取得用更新日時
            $updatedAt = $ticket->getUpdatedAt();
            if (empty($updatedAt))
                $updatedAt = $ticket->getCreatedAt();
            $this->view->assign('ticketUpdatedAt', $updatedAt);
            
            
            
            // 表画像存在取得A
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
          
            // 裏画像存在取得
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

            // ロック画像存在取得
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

            // コレクション画像
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
            
            // 一時保存画像の残骸を残さないためフォームを修正する
            $defaulutHash = $this->paramHash;
            $defaultHash['frontImageId'] = null;
            $defaultHash['backImageId'] = null;
            $form->setDefaults($defaultHash);
        }elseif($this->request->isGet()){
            // 初回アクセス時にチケット情報があれば読み出してデフォルト値とする
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
            
            // デフォルト値のセット
            $form->setDefaults($defaultHash);
            
            // 画像取得用更新日時
            $updatedAt = $ticket->getUpdatedAt();
            if (empty($updatedAt))
                $updatedAt = $ticket->getCreatedAt();
            $this->view->assign('ticketUpdatedAt', $updatedAt);
            
            // 表画像存在取得
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
            // 裏画像存在取得
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
            // ロック画像存在取得
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
		
        // viewへのセット
        $this->view->form = $form;
        $this->view->ticketId = $this->paramHash['ticketId'];
        $this->view->title = $this->_title[$this->actionName]['success'];
    }


    /**
     * チケット情報詳細
     */
    function detailAction(){

        $ticket = $this->_em->getRepository('Ticket')->findOneBy(array('id' => $this->paramHash['ticketId']));

        if (empty($ticket)){
            $e =  new Exception('<br />・チケット情報が見つかりません');
            $this->_errorMessages['title'] = $this->_title[$this->actionName]['error'];
            $this->_errorMessages['main'][] = sprintf('<p>%s</p>', $e->getMessage());
            $this->view->title = $this->_title[$this->actionName]['error'];
            $this->view->assign('errorMessages', $this->_errorMessages);
            throw $e;
        }

        $this->view->ticket = $ticket;
        $this->view->title = $this->_title[$this->actionName]['success'];
        // 画像取得用更新日時
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
     * チケット画像提供
     */
    
    public function imageAction() {
        try{
            //$this->_helper->layout->disableLayout();
            $front = Zend_Controller_Front::getInstance();
            $front->getPlugin('Zend_Layout_Controller_Plugin_Layout')->getLayout()->disableLayout();
            $this->_helper->removeHelper('viewRenderer');
            
            $req = $this->getRequest();
            $params = $req->getParams();
            
           // イベントidの取得
            $id = $params['ticketId'];
            if(empty($id))
                throw new Exception('チケットの特定が出来ません。');
            
            $type = $req->getParam('type');
            // 1: メイン画像(未使用)
            // 2: もぎり前画像
            // 3: もぎり後画像 
            if ($type != 1 && $type != 2 && $type != 3)
                throw new Exception('画像タイプの特定ができません。');
                        
            $width = $req->getParam('width');
            if(empty($width) || $width > 640)
                throw new Exception('画像サイズの特定が出来ません。'); // リファクタリング-メイン画像最大サイズでブロック
            
            // 画像取得
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
            
            // 画像取得
            $image = $this->_em->getRepository('TicketImages')
            ->findOneBy(array('ticket' => $id,'type'=> $type, 'deleteFlag' => '0'));
            
            if(!empty($image)){
                $blob = $image->getData();
                $editor = new Tixee_Image_Editor($blob, Tixee_Image_Editor::BLOB);
                $editor->resizeWidth($width);
                $imageData = $editor->getImageBlob();
            }
            else {
                // 画像が見つからない場合は画像どうするか要検討
                   
//                     $defaultImagePath;
//                     switch($type){
//                         case '1':
//                             // メイン画像
//                             $defaultImagePath = APPLICATION_PATH. "/configs/image/material/event/$type".".png";
//                             break;
//                         default:
//                             throw new Exception("不正な画像タイプのリクエストです。");
//                     }
//                     $editor = new Tixee_Image_Editor($defaultImagePath);
//                     $editor->resizeWidth($width);
//                     $imageData = $editor->getImageBlob();
            }
            
            // レスポンスオブジェクトの設定
            $this->getResponse()
            ->setHeader('Content-Type', 'image/png')
            ->setHeader('Content-Length', strlen($imageData))
            ->setHeader('Last-Modified', gmdate('D, d M Y H:i:s').' GMT')
            ->appendBody($imageData);
        } catch (Exception $e) {
            
            // リファクタリング-画面にアウトプットはしないので、適切なエラー原因のロギング
            $this->_errorMessages['title'] = $this->_title[$req->getActionName()]['error'];
            $this->_errorMessages['main'][] = sprintf('<p>%s</p>', $e->getMessage());
            $this->view->assign('errorMessages', $this->_errorMessages);
            throw $e;
        }
    }


   
    
    	
    	
    /**
     * チケット画像登録
     */
    
    public function tmpUploadImageAction() {
        $imageId = 0;
        $type = 0;
        $src = "";
        $retval = $this->_codeConf->failed;
      
        try {
            $front = Zend_Controller_Front::getInstance();
            $front->getPlugin('Zend_Layout_Controller_Plugin_Layout')->getLayout()->disableLayout();
          
            // パラメータ取得
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
                throw new Exception('ユーザーが見つかりません。', $this->_codeConf->invalid_access);
            
            // ファイルの取得
            $upload = new Zend_File_Transfer();
            $files = $upload->getFileInfo();
            
            // アップ可能なファイルは1つ
            if(1 != count($files))

                throw new Exception('ファイルのアップロードに失敗しました。', $this->_codeConf->invalid_access);
            
            $tempImage = null;
            foreach($files as $file => $info){
                // 画面からアップロードされたファイル以外は許可しない。
                if(!$upload->isUploaded($file))
                    throw new Exception('不正なアップロード行為です。', $this->_codeConf->invalid_access);
                
                // バリデーション
                $editor = new Tixee_Image_Editor($info['tmp_name']);
                if(!$editor->valid())
                    throw new Exception('画像ファイルではありません。', $this->_codeConf->validate->failed);
                
                // ファイル名取得
                $imageName = $info['name'];
 
               // リファクタリング-チケット画像最大サイズ
                $editor->resizeSquare(640, 600);
                $imageBlob = $editor->getImageBlob();
                
                // モデルの作成
                $tempImage = new \TempImages();
                // 本登録時には使用しないため一時保存でも保存する必要なかったが、カラムがあるので一応いれておく
                $tempImage->setFileName($imageName);
                $tempImage->setData($imageBlob);
                $tempImage->setDeleteFlag(false);
                $tempImage->setCreatedAt(new \Datetime());
                $tempImage->setCreatedBy($this->auth->uid);
              
                // DB保存と反映
                $this->_em->persist($tempImage);
                $this->_em->flush();
                
                $imageId = $tempImage->getId();
            }
          
            // 既存一時保存画像が存在する場合は削除しておく
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
    
    		// パラメータ取得
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
    			throw new Exception('ユーザーが見つかりません。', $this->_codeConf->invalid_access);
    
    		// ファイルの取得
    		$upload = new Zend_File_Transfer();
    		$files = $upload->getFileInfo();
    
    		// アップ可能なファイルは1つ
    		if(1 != count($files))
    			throw new Exception('ファイルのアップロードに失敗しました。', $this->_codeConf->invalid_access);
    
    		$tempImage = null;
    		foreach($files as $file => $info){
    			// 画面からアップロードされたファイル以外は許可しない。
    			if(!$upload->isUploaded($file))
    				throw new Exception('不正なアップロード行為です。', $this->_codeConf->invalid_access);
    
    			// バリデーション
    			$editor = new Tixee_Image_Editor($info['tmp_name']);
    			if(!$editor->valid())
    				throw new Exception('画像ファイルではありません。', $this->_codeConf->validate->failed);
    
    			// ファイル名取得
    			$imageName = $info['name'];
    			// リファクタリング-チケット画像最大サイズ
    			$editor->resizeSquare(640, 600);
    			$imageBlob = $editor->getImageBlob();
    
    			// モデルの作成
    			$tempImage = new \TempImages();
    			// 本登録時には使用しないため一時保存でも保存する必要なかったが、カラムがあるので一応いれておく
    			$tempImage->setFileName($imageName);
    			$tempImage->setData($imageBlob);
    			$tempImage->setDeleteFlag(false);
    			$tempImage->setCreatedAt(new \Datetime());
    			$tempImage->setCreatedBy($this->auth->uid);
    
    			// DB保存と反映
    			$this->_em->persist($tempImage);
    			$this->_em->flush();
    
    			$imageId = $tempImage->getId();
    		}
    
    		// 既存一時保存画像が存在する場合は削除しておく
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
     * 一時保存画像提供
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
            // イベントidの取得
            $id = $params['imageid'];
            $width = $params['w'];
            
            if(empty($id))
                throw new Exception('イベントの特定が出来ません。');
            
            if(empty($width) || $width > 640)
                throw new Exception('画像サイズの特定が出来ません。'); // リファクタリング-メイン画像最大サイズでブロック
            
            $image = $this->_em->getRepository('TempImages')->getTempImage($id);
            if (empty($image))
                throw new Exception('image not found');
            
            $blob = $image->getData();
            $editor = new Tixee_Image_Editor($blob, Tixee_Image_Editor::BLOB);
            $editor->resizeWidth($width);
            $imageData = $editor->getImageBlob();
            
            // レスポンスオブジェクトの設定
            $this->getResponse()
            ->setHeader('Content-Type', 'image/png')
            ->setHeader('Content-Length', strlen($imageData))
            ->setHeader('Last-Modified', gmdate('D, d M Y H:i:s').' GMT')
            ->appendBody($imageData);
        }catch (Exception $e){
            // リファクタリング-画面にアウトプットはしないので、適切なエラー原因のロギング
            $this->_errorMessages['title'] = $this->_title[$req->getActionName()]['error'];
            $this->_errorMessages['main'][] = sprintf('<p>%s</p>', $e->getMessage());
            $this->view->assign('errorMessages', $this->_errorMessages);
            throw $e;
        }
    }
    
    /**
     *  画像の本登録と一時保存画像の削除
     *  
     *  @param \Ticket $ticket チケット
     *  @param \TempImages tmpImage 画像一時保存
     *  @param int type 画像種別 1:メイン 2:もぎり前 3:もぎり後
     *  @param bigint $uid
     *  @param bool isCommit DBに書き込む場合はtrue、それ以外はfalse
     */
    private function _saveImage($ticket, $tmpImageId, $type, $uid, $isCommit = true) {
        $ti = $this->_em->getRepository('TempImages');
        $tmpImage = $ti->getTempImage($tmpImageId, true);
        
        if (empty($tmpImage))
            throw new \Exception('アップロード画像が見つかりません。');
        
        $image = $this->_em->getRepository('TicketImages')
        ->findOneBy(array('ticket' => $ticket->getId(), 'deleteFlag' => '0', 'type' => $type));
        if (empty($image)) {
            // 新規対応  
            $image = new \TicketImages();
            $image->setType($type);
            $image->setGdImagetype(0); // 使われていない
            $image->setX(0); // 使われていない
            $image->setY(0); // 使われていない
            $image->setStatus(1); // 使われていない
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
     *  画像の本登録と一時保存画像の削除
     *
     *  @param \Ticket $ticket チケット
     *  @param string title 画像タイトル
     *  @param string prob Probability
     *  @param \TempImages tmpImage 画像一時保存
     *  @param int type 画像種別 1:メイン 2:もぎり前 3:もぎり後
     *  @param bigint $uid
     *  @param bool isCommit DBに書き込む場合はtrue、それ以外はfalse
     */
    private function _saveCollectionImage($ticket, $title, $prob, $tmpImageId, $no, $uid, $isCommit = true) {
    	// 確率、画像タイトルデータのどちらかが空だった場合何もしない
    	if( empty($title) or empty($prob) ){ return; }
    
    	$image = $this->_em->getRepository('TicketCollectionImage')->findBy(array('ticket' => $ticket->getId()));
    	// 登録イメージよりも、数が多くイメージが空でない場合
    	if (count($image) < $no + 1 and !empty($tmpImageId)) {
    		// 新規対応
    		$image = new \TicketCollectionImage();
    		$image->setGdImagetype(0); // 使われていない
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
     * tsvアクション
     *
     * チケット関連のデータをTSVインポートする画面を出力するアクション。
     * 席属データの取り込みに対応する。
     *
     * @access public
     */
    public function tsvAction() {
    	// フォーム生成
    	$aname =& $this->actionName;
    	$formSeatAttr = $this->_createZendFormIni("{$aname}-seatattribute");
    
    	// formにイベントセットIDを渡す
    	$ticketId = $this->paramHash['ticketId'];
    	$formSeatAttr->getElement('ticketId')->setValue($ticketId);
    
    	// テンプレ変数セット
    	$this->view->formSeatAttribute = $formSeatAttr;
    
    	$this->view->pgurl = $this->_createControllerUrl('tsv-progress', '', '');
    	$this->view->title = $this->_title[$aname]['success'];
    }
    /**
     * tsv-eventアクション
     *
     * イベントセット関連のイベントデータをTSVインポートするアクション。
     *
     * @access public
     */
    public function tsvSeatattributeAction() {
    	$this->_saveTsvData('seatAttribute', '席属情報');
    }
    
    /**
     * TSVファイルのDB保存
     *
     * TSVファイルをデータに展開してDBへ格納する。
     *
     * @access private
     *
     * @param string $type  TSV処理タイプ
     * @param string $title 処理完了時のタイトル指定
     */
    private function _saveTsvData($type, $title) {
    	try {
    		// パラメータ取得
    		$form = $this->_createZendFormIni($this->actionName);
    
    		// CSVアップロード処理
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
    
    		// TSVチェック
    		$tsvAdapter = $form->getElement('tsv')->getTransferAdapter();
    		$tsvInfo    = $tsvAdapter->getFileInfo('tsv');
    		$tsvInfo    = $tsvInfo['tsv'];
    		$tsvWorker  = new Tixee_Tsv_Import_SeatAttribute($this->_em);
    
    		if (!$tsvWorker->isValidTsvType($tsvInfo['type'])) {
    			throw new Exception("利用できないファイルタイプです（{$tsvInfo['type']}）");
    		}
    
    		// 必要な情報をDB取得
    		$em =& $this->_em;
    
    		$ticket  = $em->getRepository('Ticket')->findOneBy(array('id' => $this->paramHash['ticketId']));
    		if (empty($ticket)) {
    			throw new Exception('チケットが見つかりません。');
    		}
    
    		$user = $em->getRepository('User')->findOneBy(array('id' => $this->auth->uid));
    		if (empty($user)) {
    			throw new Exception('登録者（ログイン中）のユーザー情報が見つかりません。');
    		}
    
    		// イベントデータ登録
    		$method = 'import' . ucfirst(strtolower($type));
    		if (!method_exists($tsvWorker, $method)) {
    			throw new Exception('サポートされていない処理タイプです。');
    		}
    
    		$ret = $tsvWorker->$method($ticket, $user, $tsvInfo['tmp_name']);
    		@unlink($tsvInfo['tmp_name']); // ゴミ掃除
    
    		// 結果出力
    		$msg = sprintf(
    				'%1$s登録完了： %1$s総数=%2$d, 登録=%3$d, 失敗=%4$d',
    				($title != '') ? $title : 'データ',
    				$ret['total'],
    				$ret['success'],
    				$ret['total'] - $ret['success']
    		);
    		if(!empty($ret['error'])) {
    			$msg .= "<br />";
    			foreach ($ret['error'] as $error) {
    				$msg .= "<b>{$error['line']}行目 : {$error['error']}</b><br />";
    			}
    		}
    	}
    	catch (Exception $e) {
    		$msg = 'CSV登録に失敗： ' . $e->getMessage();
    	}
    
    	// レンダリング停止
    	$this->_helper->removeHelper('viewRenderer');
    	$this->_helper->layout->disableLayout();
    
    	// アップロード結果を応答
    	$res = $this->getResponse();
    	$res->setHeader('Content-Type', 'text/plain');
    	$res->setHeader('Content-Length', strlen($msg));
    	$res->setBody($msg);
    
    	$res->sendResponse();
    	exit();
    }
    
    /**
     * seatattrアクション
     *
     * 座席情報のフォーマットを設定するアクション。
     *
     * @access public
     */
    public function seatFormatAction() {
    	$ticketId = $this->paramHash['ticketId'];
    
    	$ticket = $this->_em->getRepository('Ticket')
    	->findOneBy(array('id' => $ticketId, 'deleteFlag' => '0'));
    	if (empty($ticket)){
    		$e =  new Exception('<br />・チケット情報が見つかりません');
    		$this->_errorMessages['title'] = $this->_title[$this->actionName]['error'];
    		$this->_errorMessages['main'][] = sprintf('<p>%s</p>', $e->getMessage());
    		$this->view->title = $this->_title[$this->actionName]['error'];
    		$this->view->assign('errorMessages', $this->_errorMessages);
    		throw $e;
    	}
    
    	// フォーム生成
    	$form = $this->_createZendFormIni($this->actionName);
    
    	// テンプレ変数セット
    	$this->view->formSeatAttribute = $form;
    
    	if($this->request->isPost() && $form->isValidPartial($this->paramHash)) {
    		// 保存データのセット
    		$now = new \Datetime();
    		// データ更新
    		$ticket->setSeatAttrRegexp($this->paramHash['seatFormat']);
    		$ticket->setUpdatedAt($now);
    		$ticket->setUpdatedBy($this->auth->uid);
    
    		$this->_em->persist($ticket); // persist処理のおまじない
    		$this->_em->flush(); // DB反映(実際のupdate)
    
    		// 完了メッセージのセット
    		$this->view->completeMessage = $this->viewMessages['complete']['edit'];
    	}
    	elseif($this->request->isGet()) {
    		// 初回アクセス時にチケット情報があれば読み出してデフォルト値とする
    		$defaultHash = array();
    		$seatFormat = $ticket->getSeatAttrRegexp();
    		if (empty($seatFormat)) {
    			$seatFormat = "";
    		}
    		$defaultHash['seatFormat'] = $seatFormat;
    
    		// デフォルト値のセット
    		$form->setDefaults($defaultHash);
    
    	}
    
    	// viewへのセット
    	$this->view->form = $form;
    	$this->view->ticketId = $this->paramHash['ticketId'];
    	$this->view->title = $this->_title[$this->actionName]['success'];
    }
 
}

