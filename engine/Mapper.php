<?php
// engine/Mapper.php

require_once __DIR__ . '/Normalizer.php';

class Mapper {

    public static function generateMergeJobs($order, $details) {
        $jobs = [];

        if ($order['package_type'] === '1-package') {
            $isLelaki = ($order['side_type'] === 'lelaki');
            $sideLabel = $isLelaki ? 'LELAKI' : 'PEREMPUAN';

            $jobs[] = [
                'job_id'          => $order['order_id'], // Standard Order ID for 1-package
                'order_id'        => $order['order_id'],
                'side'            => $sideLabel,
                'design_code'     => KKKNormalizer::cleanDesignCode($isLelaki ? $details['design_code_groom'] : $details['design_code_bride']),
                'card_title'      => KKKNormalizer::cleanName($isLelaki ? $details['card_title_groom'] : $details['card_title_bride']),
                'namapengantin1'  => KKKNormalizer::cleanName($isLelaki ? $details['groom_name'] : $details['bride_name']),
                'namapengantin2'  => KKKNormalizer::cleanName($isLelaki ? $details['bride_name'] : $details['groom_name']),
                'namabapa'        => KKKNormalizer::cleanName($isLelaki ? $details['groom_father'] : $details['bride_father']),
                'namaibu'         => KKKNormalizer::cleanName($isLelaki ? $details['groom_mother'] : $details['bride_mother']),
                'tarikh'          => KKKNormalizer::formatDate($isLelaki ? $details['groom_event_date'] : $details['bride_event_date']),
                'tarikh_hijri'    => KKKNormalizer::cleanName($isLelaki ? $details['groom_hijri_date'] : $details['bride_hijri_date']),
                'masa'            => KKKNormalizer::cleanName($isLelaki ? $details['groom_event_time'] : $details['bride_event_time']),
                'venue'           => KKKNormalizer::cleanAddress($isLelaki ? $details['groom_venue'] : $details['bride_venue']),
                'alamat'          => KKKNormalizer::cleanAddress($isLelaki ? $details['groom_address'] : $details['bride_address']),
                'maps_url'        => KKKNormalizer::cleanUrl($isLelaki ? $details['groom_maps_url'] : $details['bride_maps_url']),
                'contact_persons' => self::formatContacts($isLelaki ? $details['groom_contacts'] : $details['bride_contacts']),
            ];

        } else if ($order['package_type'] === '2-package') {

            // Job 1: Pakej Lelaki (Collision-safe ID: ORDERID-L)
            $jobs[] = [
                'job_id'          => $order['order_id'] . '-L',
                'order_id'        => $order['order_id'],
                'side'            => 'LELAKI',
                'design_code'     => KKKNormalizer::cleanDesignCode($details['design_code_groom']),
                'card_title'      => KKKNormalizer::cleanName($details['card_title_groom']),
                'namapengantin1'  => KKKNormalizer::cleanName($details['groom_name']),
                'namapengantin2'  => KKKNormalizer::cleanName($details['bride_name']),
                'namabapa'        => KKKNormalizer::cleanName($details['groom_father']),
                'namaibu'         => KKKNormalizer::cleanName($details['groom_mother']),
                'tarikh'          => KKKNormalizer::formatDate($details['groom_event_date']),
                'tarikh_hijri'    => KKKNormalizer::cleanName($details['groom_hijri_date']),
                'masa'            => KKKNormalizer::cleanName($details['groom_event_time']),
                'venue'           => KKKNormalizer::cleanAddress($details['groom_venue']),
                'alamat'          => KKKNormalizer::cleanAddress($details['groom_address']),
                'maps_url'        => KKKNormalizer::cleanUrl($details['groom_maps_url']),
                'contact_persons' => self::formatContacts($details['groom_contacts']),
            ];

            // Job 2: Pakej Perempuan (Collision-safe ID: ORDERID-P)
            $jobs[] = [
                'job_id'          => $order['order_id'] . '-P',
                'order_id'        => $order['order_id'],
                'side'            => 'PEREMPUAN',
                'design_code'     => KKKNormalizer::cleanDesignCode($details['design_code_bride']),
                'card_title'      => KKKNormalizer::cleanName($details['card_title_bride']),
                'namapengantin1'  => KKKNormalizer::cleanName($details['bride_name']),
                'namapengantin2'  => KKKNormalizer::cleanName($details['groom_name']),
                'namabapa'        => KKKNormalizer::cleanName($details['bride_father']),
                'namaibu'         => KKKNormalizer::cleanName($details['bride_mother']),
                'tarikh'          => KKKNormalizer::formatDate($details['bride_event_date']),
                'tarikh_hijri'    => KKKNormalizer::cleanName($details['bride_hijri_date']),
                'masa'            => KKKNormalizer::cleanName($details['bride_event_time']),
                'venue'           => KKKNormalizer::cleanAddress($details['bride_venue']),
                'alamat'          => KKKNormalizer::cleanAddress($details['bride_address']),
                'maps_url'        => KKKNormalizer::cleanUrl($details['bride_maps_url']),
                'contact_persons' => self::formatContacts($details['bride_contacts']),
            ];
        }

        return $jobs;
    }

    private static function formatContacts($contactsJson) {
        if (empty($contactsJson)) return '';
        $contacts = is_array($contactsJson) ? $contactsJson : json_decode($contactsJson, true);
        if (!is_array($contacts)) return '';

        $formatted = [];
        foreach (array_slice($contacts, 0, 3) as $c) { // Restrict max 3 contacts
            if (!empty($c['name']) && !empty($c['phone'])) {
                $name = KKKNormalizer::cleanName($c['name']);
                $phone = KKKNormalizer::cleanPhone($c['phone']);
                $relation = !empty($c['relation']) ? " (" . KKKNormalizer::cleanName($c['relation']) . ")" : "";
                $formatted[] = "{$name}{$relation}: {$phone}";
            }
        }
        return implode(" | ", $formatted);
    }
}