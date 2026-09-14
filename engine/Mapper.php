<?php
// engine/Mapper.php

class Mapper {

    /**
     * Maps form details into standardized photoshop_merge_jobs rows.
     *
     * @param array $order Primary order data (order_id, package_type, side_type)
     * @param array $details Saved customer details array
     * @return array Array of job definitions ready for SQL insertion
     */
    public static function generateMergeJobs(array $order, array $details): array {
        $jobs = [];
        $orderId = $order['order_id'];
        $packageType = $order['package_type'] ?? '1-package';
        $sideType = $order['side_type'] ?? 'lelaki';

        // Helper: Format contacts array into "Name: Phone" lines for Photoshop layers
        $formatContacts = function($contacts) {
            if (empty($contacts)) return null;
            if (is_string($contacts)) {
                $contacts = json_decode($contacts, true) ?: [];
            }
            $lines = [];
            foreach ($contacts as $c) {
                if (!empty($c['name']) && !empty($c['phone'])) {
                    $lines[] = trim($c['name']) . ": " . trim($c['phone']);
                }
            }
            return !empty($lines) ? implode("\n", $lines) : null;
        };

        // Determine ordering of couple names based on side:
        // - Lelaki: Groom top, Bride bottom
        // - Perempuan: Bride top, Groom bottom
        $getCoupleNames = function($side) use ($details) {
            if ($side === 'perempuan') {
                return [
                    'nama1' => $details['bride_name'] ?? null,
                    'nama2' => $details['groom_name'] ?? null,
                    'short1' => $details['bride_abbrev'] ?? null,
                    'short2' => $details['groom_abbrev'] ?? null,
                ];
            }
            // Default: Lelaki or Both single-card fallback
            return [
                'nama1' => $details['groom_name'] ?? null,
                'nama2' => $details['bride_name'] ?? null,
                'short1' => $details['groom_abbrev'] ?? null,
                'short2' => $details['bride_abbrev'] ?? null,
            ];
        };

        if ($packageType === '2-package') {
            // --- JOB 1: PIHAK LELAKI ---
            $namesL = $getCoupleNames('lelaki'); // Groom top
            $jobs[] = [
                ':job_id'          => $orderId . '-L',
                ':order_id'        => $orderId,
                ':side'            => 'lelaki',
                ':design_code'     => $details['design_code_groom'] ?? null,
                ':card_title'      => $details['card_title_groom'] ?? 'Walimatul Urus',
                ':namapengantin1'  => $namesL['nama1'], // Groom Full Name
                ':namapengantin2'  => $namesL['nama2'], // Bride Full Name
                ':namabapa'        => $details['groom_father'] ?? null,
                ':namaibu'         => $details['groom_mother'] ?? null,
                ':tarikh'          => $details['groom_event_date'] ?? null,
                ':tarikh_hijri'    => $details['groom_hijri_date'] ?? null,
                ':masa'            => $details['groom_event_time'] ?? null,
                ':venue'           => $details['groom_venue'] ?? null,
                ':alamat'          => $details['groom_address'] ?? null,
                ':maps_url'        => $details['groom_maps_url'] ?? null,
                ':contact_persons' => $formatContacts($details['groom_contacts'] ?? []),
            ];

            // --- JOB 2: PIHAK PEREMPUAN ---
            $namesP = $getCoupleNames('perempuan'); // Bride top
            $jobs[] = [
                ':job_id'          => $orderId . '-P',
                ':order_id'        => $orderId,
                ':side'            => 'perempuan',
                ':design_code'     => $details['design_code_bride'] ?? ($details['design_code_groom'] ?? null),
                ':card_title'      => $details['card_title_bride'] ?? ($details['card_title_groom'] ?? 'Walimatul Urus'),
                ':namapengantin1'  => $namesP['nama1'], // Bride Full Name
                ':namapengantin2'  => $namesP['nama2'], // Groom Full Name
                ':namabapa'        => $details['bride_father'] ?? null,
                ':namaibu'         => $details['bride_mother'] ?? null,
                ':tarikh'          => $details['bride_event_date'] ?? ($details['groom_event_date'] ?? null),
                ':tarikh_hijri'    => $details['bride_hijri_date'] ?? ($details['groom_hijri_date'] ?? null),
                ':masa'            => $details['bride_event_time'] ?? ($details['groom_event_time'] ?? null),
                ':venue'           => $details['bride_venue'] ?? ($details['groom_venue'] ?? null),
                ':alamat'          => $details['bride_address'] ?? ($details['groom_address'] ?? null),
                ':maps_url'        => $details['bride_maps_url'] ?? ($details['groom_maps_url'] ?? null),
                ':contact_persons' => $formatContacts($details['bride_contacts'] ?? []),
            ];

        } else {
            // --- 1-PACKAGE (SINGLE CARD) ---
            $names = $getCoupleNames($sideType);
            
            // Choose parent/event data based on sideType choice
            $isPerempuan = ($sideType === 'perempuan');
            $father = $isPerempuan ? ($details['bride_father'] ?? $details['groom_father'] ?? null) : ($details['groom_father'] ?? null);
            $mother = $isPerempuan ? ($details['bride_mother'] ?? $details['groom_mother'] ?? null) : ($details['groom_mother'] ?? null);
            $eventDate = $isPerempuan ? ($details['bride_event_date'] ?? $details['groom_event_date'] ?? null) : ($details['groom_event_date'] ?? null);
            $hijriDate = $isPerempuan ? ($details['bride_hijri_date'] ?? $details['groom_hijri_date'] ?? null) : ($details['groom_hijri_date'] ?? null);
            $eventTime = $isPerempuan ? ($details['bride_event_time'] ?? $details['groom_event_time'] ?? null) : ($details['groom_event_time'] ?? null);
            $venue = $isPerempuan ? ($details['bride_venue'] ?? $details['groom_venue'] ?? null) : ($details['groom_venue'] ?? null);
            $address = $isPerempuan ? ($details['bride_address'] ?? $details['groom_address'] ?? null) : ($details['groom_address'] ?? null);
            $mapsUrl = $isPerempuan ? ($details['bride_maps_url'] ?? $details['groom_maps_url'] ?? null) : ($details['groom_maps_url'] ?? null);
            $contacts = $isPerempuan ? ($details['bride_contacts'] ?? $details['groom_contacts'] ?? []) : ($details['groom_contacts'] ?? []);

            $jobs[] = [
                ':job_id'          => $orderId,
                ':order_id'        => $orderId,
                ':side'            => $sideType,
                ':design_code'     => $details['design_code_groom'] ?? null,
                ':card_title'      => $details['card_title_groom'] ?? 'Walimatul Urus',
                ':namapengantin1'  => $names['nama1'],
                ':namapengantin2'  => $names['nama2'],
                ':namabapa'        => $father,
                ':namaibu'         => $mother,
                ':tarikh'          => $eventDate,
                ':tarikh_hijri'    => $hijriDate,
                ':masa'            => $eventTime,
                ':venue'           => $venue,
                ':alamat'          => $address,
                ':maps_url'        => $mapsUrl,
                ':contact_persons' => $formatContacts($contacts),
            ];
        }

        return $jobs;
    }
}