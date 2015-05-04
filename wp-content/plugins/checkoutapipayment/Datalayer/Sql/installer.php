<?php
class Datalayer_Sql_installer
{
	public static function install()
	{
		self::_createTables();
	}

	private  static function _createTables() {
		global $wpdb;

		//$wpdb->hide_errors();

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

		dbDelta( self::_getSchema() );
	}

	private static function _getSchema()
	{
		global $wpdb;

		$collate = '';

		if ( $wpdb->has_cap ( 'collation' ) ) {
			if ( !empty( $wpdb->charset ) ) {
				$collate .= "DEFAULT CHARACTER SET $wpdb->charset";
			}
			if ( !empty( $wpdb->collate ) ) {
				$collate .= " COLLATE $wpdb->collate";
			}
		}

		return "CREATE TABLE {$wpdb->prefix}cko_users_to_customerId (
				  id bigint(20) NOT NULL auto_increment,
				  user_id bigint(20) NOT NULL,
				  customer_id varchar(200) NOT NULL,
				  PRIMARY KEY  (id),
				  KEY user_id (user_id)
				) $collate;
				CREATE TABLE {$wpdb->prefix}cko_customerId_to_cardId
				  (id bigint(20) NOT NULL auto_increment,
				  card_id varchar(200) NOT NULL,
				  last4 varchar(4) NOT NULL,
				  expiryMonth varchar(2) NOT NULL,
				  expiryYear varchar(4) NOT NULL,
				  PRIMARY KEY  (id),
				  KEY card_id (card_id)
				  ) $collate;
				";
	}

	public static function saveChargeDetails ($respondCharge,$user_ID)
	{
		global $wpdb;
		$cardId = $respondCharge->getCard()->getId();
		$last4 = $respondCharge->getCard()->getLast4();
		$expiryMonth = $respondCharge->getCard()->getExpiryMonth();
		$customerId = $respondCharge->getCard()->getCustomerId();
		$expiryYear = $respondCharge->getCard()->getExpiryYear();

		$insert = "INSERT INTO {$wpdb->prefix}cko_customerId_to_cardId
								(card_id, last4, expiryMonth, expiryYear) VALUES (
								'$cardId','$last4','$expiryMonth','$expiryYear')";
		$results = $wpdb->query( $insert );

		$insert = "INSERT INTO {$wpdb->prefix}cko_users_to_customerId
								(user_id, customer_id) VALUES (
								'$user_ID','$customerId')";
		$results = $wpdb->query( $insert );
	}
}