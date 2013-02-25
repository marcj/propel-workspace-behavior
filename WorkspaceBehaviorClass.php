<?php

abstract class WorkspaceBehaviorClass extends BaseObject {

    public function __construct(){

        $peer = $this->getPeer();
        call_user_func(array($this, 'setWorkspaceId'), $peer::getWorkspaceId());

    }

}
