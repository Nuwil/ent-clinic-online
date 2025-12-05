<?php
/**
 * SlotGenerator - produces candidate appointment slots for a clinic day
 */
class SlotGenerator
{
    // Generate slots for a date given clinic config
    public static function generateSlotsForDate(string $date, array $config): array
    {
        $tz = new DateTimeZone(date_default_timezone_get());
        $start = new DateTime($date . ' ' . $config['working_hours']['start'], $tz);
        $end = new DateTime($date . ' ' . $config['working_hours']['end'], $tz);

        $lunchStart = new DateTime($date . ' ' . $config['lunch']['start'], $tz);
        $lunchEnd = new DateTime($date . ' ' . $config['lunch']['end'], $tz);

        $slots = [];

        // Helper to push a slot
        $push = function(DateTime $s, int $duration, string $type) use (&$slots) {
            $e = (clone $s)->add(new DateInterval('PT' . $duration . 'M'));
            $slots[] = [
                'start' => $s->format('Y-m-d H:i:s'),
                'end' => $e->format('Y-m-d H:i:s'),
                'type' => $type
            ];
        };

        // Create follow-up slots (short slots) every duration+buffer
        $fDur = $config['types']['follow_up']['duration'];
        $fStep = $fDur + $config['types']['follow_up']['buffer'];
        $cursor = clone $start;
        while ($cursor < $end) {
            if ($cursor >= $lunchStart && $cursor < $lunchEnd) { $cursor = clone $lunchEnd; continue; }
            // ensure enough room
            $endCandidate = (clone $cursor)->add(new DateInterval('PT' . $fDur . 'M'));
            if ($endCandidate > $end) break;
            $push($cursor, $fDur, 'follow_up');
            $cursor->add(new DateInterval('PT' . $fStep . 'M'));
        }

        // Create new patient slots (larger) every duration+buffer
        $nDur = $config['types']['new_patient']['duration'];
        $nStep = $nDur + $config['types']['new_patient']['buffer'];
        $cursor = clone $start;
        while ($cursor < $end) {
            if ($cursor >= $lunchStart && $cursor < $lunchEnd) { $cursor = clone $lunchEnd; continue; }
            $endCandidate = (clone $cursor)->add(new DateInterval('PT' . $nDur . 'M'));
            if ($endCandidate > $end) break;
            $push($cursor, $nDur, 'new_patient');
            $cursor->add(new DateInterval('PT' . $nStep . 'M'));
        }

        // Create procedure candidate slots: look for windows >= procedure duration + buffer
        $pDur = $config['types']['procedure']['duration'];
        $pStep = 30; // try candidate every 30 minutes
        $cursor = clone $start;
        while ($cursor < $end) {
            if ($cursor >= $lunchStart && $cursor < $lunchEnd) { $cursor = clone $lunchEnd; continue; }
            $endCandidate = (clone $cursor)->add(new DateInterval('PT' . ($pDur + $config['types']['procedure']['buffer']) . 'M'));
            if ($endCandidate > $end) break;
            // ensure procedure fits before lunch
            if ($cursor < $lunchStart && $endCandidate > $lunchStart) { $cursor->add(new DateInterval('PT' . $pStep . 'M')); continue; }
            $push($cursor, $pDur, 'procedure');
            $cursor->add(new DateInterval('PT' . $pStep . 'M'));
        }

        // Deduplicate by start time preferring shorter slots (follow_up) where exact matches exist
        $map = [];
        usort($slots, function($a,$b){ return strcmp($a['start'],$b['start']); });
        foreach ($slots as $s) {
            if (!isset($map[$s['start']])) {
                $map[$s['start']] = $s;
            } else {
                // prefer follow_up if times match (keeps short slot option)
                if ($s['type'] === 'follow_up') $map[$s['start']] = $s;
            }
        }

        $result = array_values($map);
        usort($result, function($a,$b){ return strcmp($a['start'],$b['start']); });
        return $result;
    }
}
