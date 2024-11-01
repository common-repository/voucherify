<?php

namespace Voucherify\Wordpress\Models;

use Voucherify\Wordpress\Synchronization\Services\CustomerService;

class CustomerModel
{
    public function getBillingDetailsList($offset, $limit = 1000)
    {
        global $wpdb;

        return $wpdb->get_results(
            "SELECT u.ID as source_id,"
            . "fn.meta_value as first_name,"
            . "ln.meta_value as last_name,"
            . "e.meta_value as email,"
            . "p.meta_value as phone,"
            . "a1.meta_value as address1,"
            . "a2.meta_value as address2,"
            . "zip.meta_value as postal_code,"
            . "ci.meta_value as city,"
            . "s.meta_value as state,"
            . "co.meta_value as country "
            . "FROM {$wpdb->users} u "
            . "LEFT JOIN {$wpdb->usermeta} fn on fn.user_id = u.ID and fn.meta_key = 'billing_first_name' "
            . "LEFT JOIN {$wpdb->usermeta} ln on ln.user_id = u.ID and ln.meta_key = 'billing_last_name' "
            . "LEFT JOIN {$wpdb->usermeta} e on e.user_id = u.ID and e.meta_key = 'billing_email' "
            . "LEFT JOIN {$wpdb->usermeta} p on p.user_id = u.ID and p.meta_key = 'billing_phone' "
            . "LEFT JOIN {$wpdb->usermeta} a1 on a1.user_id = u.ID and a1.meta_key = 'billing_address_1' "
            . "LEFT JOIN {$wpdb->usermeta} a2 on a2.user_id = u.ID and a2.meta_key = 'billing_address_2' "
            . "LEFT JOIN {$wpdb->usermeta} zip on zip.user_id = u.ID and zip.meta_key = 'billing_postcode' "
            . "LEFT JOIN {$wpdb->usermeta} ci on ci.user_id = u.ID and ci.meta_key = 'billing_city' "
            . "LEFT JOIN {$wpdb->usermeta} s on s.user_id = u.ID and s.meta_key = 'billing_state' "
            . "LEFT JOIN {$wpdb->usermeta} co on co.user_id = u.ID and co.meta_key = 'billing_country' "
            . "WHERE NOT EXISTS ("
            . "   SELECT 1 FROM {$wpdb->usermeta} vcrf_ids"
            . "   WHERE vcrf_ids.user_id = u.ID"
            . "   AND vcrf_ids.meta_key = '" . CustomerService::VCRF_ID_META_KEY_NAME . "' LIMIT 1"
            . ") AND EXISTS("
            . "  SELECT 1 FROM {$wpdb->usermeta} um WHERE um.user_id = u.ID and um.meta_key like 'billing_%' LIMIT 1"
            . ") LIMIT $limit OFFSET $offset",
            ARRAY_A
        );
    }
}
