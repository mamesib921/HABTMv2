<?php


    /* 
    * HABTM
    * リレーションで保存を行うときに使う
    * 編集時には子の主キーをhiddenで渡すこと
    * フォーマット： Model.id.0.value , Model.foreign_id.0.value 
    **/
    protected function _editHABTM($id = 0, $option = array()) {
        $option = array_merge(array('saveAll' => array('deep' => true),// リレーションを保存するとき使用する
                                    'saveAllClass' => 'JobInfoMstFeature',// リレーション名
                                    'associationForeignKey' => 'job_info_id',// リレーションID 1
                                    'saveAll_foreign_key' => 'mst_feature_id', // リレーションID 2
                                    'save' => true,// 通常保存 ToDo::必須(falseだとidがnullになる)
                                    'create' => null,
                                    'callback' => null,
                                    'redirect' => array('action' => 'index')),
                              $option);
        extract($option);

        if ($this->request->is(array('post', 'put'))
            && $this->request->data //post_max_sizeを越えた場合の対応(空になる)
            ) {

            $this->{$this->modelName}->id = $id;
            $this->request->data[$this->modelName][$this->{$this->modelName}->primaryKey] = $id;

            /**
             * 添付ファイルのバリデーションを行うためここで実行
             */
            $this->{$this->modelName}->set($this->request->data);
            $isValid = $this->{$this->modelName}->validates();
            // リレーションModel
            $this->{$saveAllClass}->set($this->request->data);
            $isForeValid = $this->{$saveAllClass}->validates();

            // リレーション 親と子のバリエーションを行う
            if ($isValid&&$isForeValid) {
                $id = $this->{$this->modelName}->id;

                $this->{$this->modelName}->create();
                // 親を保存
                if($save) {
                    $trust = $this->{$this->modelName}->trustList();
                    $r = $this->{$this->modelName}->save($this->request->data, false, $trust);
                    // 子にidを渡すため保存後のidを保持
                    $last_id = $this->{$this->modelName}->getLastInsertID();
                }
                if ($saveAll&&$save) {
                    $setData = $this->request->data;
                    // 親のidがあるか
                    if (empty($last_id)) {
                        $last_id = $id;
                    }
                    // 送信値の外部キーを保存できるフォーマット変換
                    $ids=array();
                    if(!empty($this->request->data[$saveAllClass][$saveAll_foreign_key])) {
                        foreach ($this->request->data[$saveAllClass][$saveAll_foreign_key] as $k => $v) {
                            if(!empty($this->request->data[$saveAllClass]['id'][$k])) {
                            @$d[$k]['id'] = $this->request->data[$saveAllClass]['id'][$k];
                            $ids[] = $this->request->data[$saveAllClass]['id'][$k];
                            }
                            @$d[$k][$associationForeignKey] = $last_id;
                            @$d[$k][$saveAll_foreign_key] = $v;
                        }
                    }

                    // 比較対象DB値出力
                    $foreigns = $this->{$saveAllClass}->find("all",array('conditions' => array($associationForeignKey => $last_id),'order' => $saveAllClass.'.id asc'));
                    $foreign_ids = array();
                    // 比較対象DB値設定
                    if(!empty($foreigns)) {
                    $foreign_ids = Hash::extract($foreigns, '{n}.'.$saveAllClass.'.'.$saveAll_foreign_key);
                    }
                    // 送信値とDBを比較して差分を削除する
                    $diff = Hash::diff($ids,$foreign_ids);
                    if(!empty($diff)) {
                        foreach($diff as $l => $df) {
                           $dl  = $this->{$saveAllClass}->deleteAll(array($saveAllClass.'.'.$associationForeignKey => $last_id,$saveAllClass.'.'.$saveAll_foreign_key => $df), false);
                        }
                    } 

                    // リレーションを保存するデータをセット
                    @$setData[$saveAllClass] = $d;
                    if(!empty($setData[$saveAllClass])) {
                    $r = $this->{$saveAllClass}->saveAll($setData[$saveAllClass], $saveAll);
                    }
                } 

                if ($r) {
                    if ($callback) {
                        $callback($this->{$this->modelName}->id);
                    }
                    if ($redirect) {
                        $this->redirect($redirect);
                    }
                }

            } else {
                // 送信値の外部キーをFormに保持できるように変換
                if(!empty($this->request->data[$saveAllClass][$saveAll_foreign_key])) {
                    foreach ($this->request->data[$saveAllClass][$saveAll_foreign_key] as $k => $v) {
                        if(!empty($this->request->data[$saveAllClass]['id'][$k])) {
                            @$this->request->data[$saveAllClass][$k]['id'] = $this->request->data[$saveAllClass]['id'][$k];
                        }
                        @$this->request->data[$saveAllClass][$k][$saveAll_foreign_key] = $v;
                    }
                    // 変換後のゴミデータ消去(重要)
                    unset($this->request->data[$saveAllClass]['id']);
                    unset($this->request->data[$saveAllClass][$saveAll_foreign_key]);
                }
                
                $this->set('data', $this->request->data);
                $this->Flash->set('正しく入力されていない項目があります');
            }
        } else {
            $this->{$this->modelName}->id = $id;
            if ($create) {
                $this->request->data = $create;
            } elseif ($this->{$this->modelName}->exists()) {
                $this->request->data = $this->{$this->modelName}->read(null, $id);
            } else {
                $this->request->data = $this->{$this->modelName}->create();
                if (!array_key_exists($this->{$this->modelName}->primaryKey, $this->request->data[$this->modelName])) {
                    $this->request->data[$this->modelName][$this->{$this->modelName}->primaryKey] = null;
                }
            }
            $this->set('data', $this->request->data);
        }
    }
