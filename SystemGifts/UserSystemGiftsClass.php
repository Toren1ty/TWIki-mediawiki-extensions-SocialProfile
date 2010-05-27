<?php
/**
 * Class for managing awards (a.k.a system gifts)
 */
class UserSystemGifts {

	/**
	 * All member variables should be considered private
	 * Please use the accessor functions
	 */

	/**#@+
	 * @private
	 */
	var $user_id;			# Text form (spaces not underscores) of the main part
	var $user_name;			# Text form (spaces not underscores) of the main part

	/**
	 * Constructor
	 * @private
	 */
	/* private */ function __construct( $username ) {
		$title1 = Title::newFromDBkey( $username );
		$this->user_name = $title1->getText();
		$this->user_id = User::idFromName( $this->user_name );
	}

	public function sendSystemGift( $gift_id, $email = true ) {
		global $wgMemc;

		if ( $this->doesUserHaveGift( $this->user_id, $gift_id ) ) {
			return '';
		}

		$dbw = wfGetDB( DB_MASTER );
		$dbw->insert(
			'user_system_gift',
			array(
				'sg_gift_id' => $gift_id,
				'sg_user_id' => $this->user_id,
				'sg_user_name' => $this->user_name,
				'sg_status' => 1,
				'sg_date' => date( 'Y-m-d H:i:s' ),
			),
			__METHOD__
		);
		$sg_gift_id = $dbw->insertId();
		$this->incGiftGivenCount( $gift_id );

		// Add to new gift count cache for receiving user
		$this->incNewSystemGiftCount( $this->user_id );

		if ( $email && !empty( $sg_gift_id ) ) {
			$this->sendGiftNotificationEmail( $this->user_id, $gift_id );
		}
		$wgMemc->delete( wfMemcKey( 'user', 'profile', 'system_gifts', $this->user_id ) );
		return $sg_gift_id;
	}

	public function sendGiftNotificationEmail( $user_id_to, $gift_id ) {
		$gift = SystemGifts::getGift( $gift_id );
		$user = User::newFromId( $user_id_to );
		$user->loadFromDatabase();
		if ( $user->isEmailConfirmed() && $user->getIntOption( 'notifygift', 1 ) ) {
			wfLoadExtensionMessages( 'SystemGifts' );
			$gifts_link = SpecialPage::getTitleFor( 'ViewSystemGifts' );
			$update_profile_link = SpecialPage::getTitleFor( 'UpdateProfile' );
			$subject = wfMsgExt( 'system_gift_received_subject', 'parsemag',
				$gift['gift_name']
			);
			$body = wfMsgExt( 'system_gift_received_body', 'parsemag',
				( ( trim( $user->getRealName() ) ) ? $user->getRealName() : $user->getName() ),
				$gift['gift_name'],
				$gift['gift_description'],
				$gifts_link->getFullURL(),
				$update_profile_link->getFullURL()
			);

			$user->sendMail( $subject, $body );
		}
	}

	public function doesUserHaveGift( $user_id, $gift_id ) {
		$dbr = wfGetDB( DB_SLAVE );
		$s = $dbr->selectRow(
			'user_system_gift',
			array( 'sg_status' ),
			array( 'sg_user_id' => $user_id, 'sg_gift_id' => $gift_id ),
			__METHOD__
		);
		if ( $s !== false ) {
			return true;
		}
		return false;
	}

	public function clearAllUserSystemGiftStatus() {
		$dbw = wfGetDB( DB_MASTER );
		$dbw->update( 'user_system_gift',
		/* SET */array( 'sg_status' => 0 ),
		/* WHERE */array( 'sg_user_id' => $this->user_id ),
			__METHOD__
		);
		$this->clearNewSystemGiftCountCache( $this->user_id );
	}

	static function clearUserGiftStatus( $id ) {
		$dbw = wfGetDB( DB_MASTER );
		$dbw->update( 'user_system_gift',
			/* SET */array( 'sg_status' => 0 ),
			/* WHERE */array( 'sg_id' => $id ),
			__METHOD__
		);
	}

	public function doesUserOwnGift( $user_id, $sg_id ) {
		$dbr = wfGetDB( DB_SLAVE );
		$s = $dbr->selectRow(
			'user_system_gift',
			array( 'sg_user_id' ),
			array( 'sg_id' => $sg_id ),
			__METHOD__
		);
		if ( $s !== false ) {
			if ( $user_id == $s->ug_user_id_to ) {
				return true;
			}
		}
		return false;
	}

	static function deleteGift( $ug_id ) {
		$dbw = wfGetDB( DB_MASTER );
		$dbw->delete( 'user_system_gift', array( 'sg_id' => $ug_id ), __METHOD__ );
	}

	static function getUserGift( $id ) {
		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->select(
			array( 'user_system_gift', 'system_gift' ),
			array(
				'sg_id', 'sg_user_id', 'sg_user_name', 'gift_id', 'sg_date',
				'gift_name', 'gift_description', 'gift_given_count', 'sg_status'
			),
			array( "sg_id = {$id}" ),
			__METHOD__,
			array(
				'LIMIT' => 1,
				'OFFSET' => 0
			),
			array( 'system_gift' => array( 'INNER JOIN', 'sg_gift_id = gift_id' ) )
		);
		$row = $dbr->fetchObject( $res );
		if ( $row ) {
			$gift['id'] = $row->sg_id;
			$gift['user_id'] = $row->sg_user_id;
			$gift['user_name'] = $row->sg_user_name;
			$gift['gift_count'] = $row->gift_given_count;
			$gift['timestamp'] = $row->sg_date;
			$gift['gift_id'] = $row->gift_id;
			$gift['name'] = $row->gift_name;
			$gift['description'] = $row->gift_description;
			$gift['status'] = $row->sg_status;
		}

		return $gift;
	}

	public function incNewSystemGiftCount( $user_id ) {
		global $wgMemc;
		$key = wfMemcKey( 'system_gifts', 'new_count', $user_id );
		$wgMemc->incr( $key );
	}

	public function decNewSystemGiftCount( $user_id ) {
		global $wgMemc;
		$key = wfMemcKey( 'system_gifts', 'new_count', $user_id );
		$wgMemc->decr( $key );
	}

	public function clearNewSystemGiftCountCache() {
		global $wgMemc;
		$key = wfMemcKey( 'system_gifts', 'new_count', $user_id );
		$wgMemc->set( $key, 0 );
	}

	static function getNewSystemGiftCountCache( $user_id ) {
		global $wgMemc;
		$key = wfMemcKey( 'system_gifts', 'new_count', $user_id );
		$data = $wgMemc->get( $key );
		if ( $data != '' ) {
			wfDebug( "Got new award count of $data for id $user_id from cache\n" );
			return $data;
		}
	}

	static function getNewSystemGiftCount( $user_id ) {
		global $wgMemc;
		$data = self::getNewSystemGiftCountCache( $user_id );

		if ( $data != '' ) {
			$count = $data;
		} else {
			$count = self::getNewSystemGiftCountDB( $user_id );
		}
		return $count;
	}

	static function getNewSystemGiftCountDB( $user_id ) {
		wfDebug( "Got new award count for id $user_id from DB\n" );

		global $wgMemc;
		$key = wfMemcKey( 'system_gifts', 'new_count', $user_id );
		$dbr = wfGetDB( DB_SLAVE );
		$new_gift_count = 0;
		$s = $dbr->selectRow(
			'user_system_gift',
			array( 'COUNT(*) AS count' ),
			array( 'sg_user_id' => $user_id, 'sg_status' => 1 ),
			__METHOD__
		);
		if ( $s !== false ) {
			$new_gift_count = $s->count;
		}

		$wgMemc->set( $key, $new_gift_count );

		return $new_gift_count;
	}

	public function getUserGiftList( $type, $limit = 0, $page = 0 ) {
		$dbr = wfGetDB( DB_SLAVE );

		$limitvalue = 0;
		if ( $limit > 0 ) {
			if ( $page ) {
				$limitvalue = $page * $limit - ( $limit );
			}
		}

		$res = $dbr->select(
			array( 'user_system_gift', 'system_gift' ),
			array(
				'sg_id', 'sg_user_id', 'sg_user_name', 'sg_gift_id', 'sg_date',
				'sg_status', 'gift_name', 'gift_description',
				'gift_given_count', 'UNIX_TIMESTAMP(sg_date) AS unix_time'
			),
			array( "sg_user_id = {$this->user_id}" ),
			__METHOD__,
			array(
				'ORDER BY' => 'sg_id DESC',
				'LIMIT' => $limit,
				'OFFSET' => $limitvalue
			),
			array( 'system_gift' => array( 'INNER JOIN', 'sg_gift_id = gift_id' ) )
		);

		$requests = array();
		foreach ( $res as $row ) {
			$requests[] = array(
				'id' => $row->sg_id,
				'gift_id' => $row->sg_gift_id,
				'timestamp' => ( $row->sg_date ),
				'status' => $row->sg_status,
				'user_id' => $row->sg_user_id,
				'user_name' => $row->sg_user_name,
				'gift_name' => $row->gift_name,
				'gift_description' => $row->gift_description,
				'gift_given_count' => $row->gift_given_count,
				'unix_timestamp' => $row->unix_time
			);
		}
		return $requests;
	}

	private function incGiftGivenCount( $gift_id ) {
		$dbw = wfGetDB( DB_MASTER );
		$dbw->update( 'system_gift',
			array( 'gift_given_count=gift_given_count+1' ),
			array( 'gift_id' => $gift_id ),
			__METHOD__
		);
	}

	static function getGiftCountByUsername( $user_name ) {
		$dbr = wfGetDB( DB_SLAVE );
		$user_id = User::idFromName( $user_name );
		$res = $dbr->select(
			'user_system_gift',
			array( 'COUNT(*) AS count' ),
			array( "sg_user_id = {$user_id}" ),
			__METHOD__,
			array( 'LIMIT' => 1, 'OFFSET' => 0 )
		);
		$row = $dbr->fetchObject( $res );
		$gift_count = 0;
		if ( $row ) {
			$gift_count = $row->count;
		}
		return $gift_count;
	}
}
