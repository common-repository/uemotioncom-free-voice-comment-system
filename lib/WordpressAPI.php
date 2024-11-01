<?php
class WordpressAPI {
	public $api;

	public function __construct(&$api) {
		$this->api = $api;
	}
	public function _i($text) {
		if(function_exists('wpue_i')) {
			return wpue_i($text);
		} else {
			return $text;
		}
	}

	public function exportPosts($echoProgress = false) {
		global $wpdb;
		$tname = $wpdb->prefix . 'posts';
		$twpue = $wpdb->prefix . 'wpue_posts';
		if($echoProgress) { echo('<br>'.$this->_i('Loading posts').'.'); };
		$list = $wpdb->get_results($wpdb->prepare("
			SELECT *
			FROM $tname AS p
			LEFT JOIN $twpue AS t ON t.id = p.ID
			WHERE p.post_status = %s AND t.groupId IS NULL
		", 'publish'));
		if($echoProgress) { echo('<br>'.$this->_i('Sending').'.'); };
		foreach($list as $post) {
			if($echoProgress) { echo('.'); };
			$ret = $this->api->createGroup($post->post_name, $post->post_title, $post->guid);
			if(!$ret) {
				if($echoProgress) { echo('Fail'); };
				return false;
			} else if(isset($ret['groupId'])) {
				$wpdb->insert($twpue, array('id' => $post->ID, 'groupId' => $ret['groupId']));
			}
			if($echoProgress) { echo('.'); };
		}
		return true;
	}

	public function exportComments($echoProgress = false) {
		global $wpdb;
		$tname = $wpdb->prefix . 'posts';
		$tcomm = $wpdb->prefix . 'comments';
		$twpue = $wpdb->prefix . 'wpue_posts';
		if($echoProgress) { echo('<br>'.$this->_i('Loading posts').'.'); };
		$list = $wpdb->get_results($wpdb->prepare("
			SELECT p.ID as ID, groupId
			FROM $tname AS p
			LEFT JOIN $twpue AS t ON t.id = p.ID
			WHERE p.post_status = %s AND t.groupId IS NOT NULL
		", 'publish'));
		if($echoProgress) { echo('<br>'.$this->_i('Sending').'.'); };
		foreach($list as $post) {
			if($echoProgress) { echo('.'); };
			$list = $wpdb->get_results($wpdb->prepare("
				SELECT *
				FROM $tcomm AS c
				WHERE c.comment_approved = %d AND c.comment_post_ID = %d
			", 1, $post->ID));
			if($echoProgress) { echo('.'); };

			$parsed = array();
			foreach($list as $val) {
				$parsed[] = array('content' => $val->comment_content, 'author_name' => $val->comment_author, 'author_ip' => $val->comment_author_IP, 'date_gmt' => $val->comment_date_gmt);
				if($echoProgress) { echo('.'); };
			}

			if(count($parsed)) {
				$ret = $this->api->createComments($post->groupId, $parsed);
				if(!$ret) {
					if($echoProgress) { echo('Fail'); };
					return false;
				} else if(isset($ret['groupId'])) {
					$wpdb->insert($twpue, array('id' => $post->ID, 'groupId' => $ret['groupId']));
				}
				if($echoProgress) { echo('.'); };
			}
		}
		return true;
	}
}
?>