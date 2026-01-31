<?php
declare(strict_types=1);

/**
 * Calendar Service Class
 * Generates iCalendar (.ics) files compatible with Apple Calendar, Outlook, and Google Calendar
 * Provides helper methods for creating calendar URLs
 * 
 * @requires PHP 8.0+ (uses typed properties)
 */
class CalendarService {
    private PDO $pdo;
    private ?array $lastFetchedEvent = null;
    
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Get the last event data fetched by generateICS or generateGoogleCalendarUrl
     * Useful for avoiding redundant database queries
     * 
     * @return array|null Last fetched event data or null
     */
    public function getLastFetchedEvent(): ?array {
        return $this->lastFetchedEvent;
    }
    
    /**
     * Generate iCalendar (.ics) file content for an event or helper slot
     * Compatible with Apple Calendar, Outlook, and Google Calendar
     * 
     * @param int $eventId Event ID
     * @param int|null $slotId Optional helper slot ID for slot-specific export
     * @return string|false iCalendar content or false on failure
     */
    public function generateICS(int $eventId, ?int $slotId = null): string|false {
        // If slot ID is provided, generate ICS for the helper slot
        if ($slotId !== null) {
            return $this->generateHelperSlotICS($eventId, $slotId);
        }
        
        // Get event from database
        $stmt = $this->pdo->prepare("
            SELECT 
                e.id, 
                e.title, 
                e.description, 
                e.event_date, 
                e.location,
                e.created_at,
                e.updated_at
            FROM events e
            WHERE e.id = ?
        ");
        
        $stmt->execute([$eventId]);
        $event = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$event) {
            error_log("Event not found: ID {$eventId}");
            $this->lastFetchedEvent = null;
            return false;
        }
        
        // Store event data for potential reuse
        $this->lastFetchedEvent = $event;
        
        // Generate unique identifier for the event
        $uid = 'event-' . $event['id'] . '@';
        if (defined('SITE_URL')) {
            $parsedUrl = parse_url(SITE_URL);
            if ($parsedUrl !== false && isset($parsedUrl['host'])) {
                $uid .= $parsedUrl['host'];
            } else {
                $uid .= 'localhost';
            }
        } else {
            $uid .= 'localhost';
        }
        
        // Format dates in iCalendar format (YYYYMMDDTHHMMSS)
        $eventDateTime = new DateTime($event['event_date']);
        $dtstart = $eventDateTime->format('Ymd\THis');
        
        // Set end time to 2 hours after start (default duration)
        $dtend = clone $eventDateTime;
        $dtend->modify('+2 hours');
        $dtendFormatted = $dtend->format('Ymd\THis');
        
        // Format created and modified timestamps
        $createdAt = new DateTime($event['created_at']);
        $dtstamp = $createdAt->format('Ymd\THis\Z');
        
        // Format description - escape special characters and handle line breaks
        $description = $this->escapeICSString($event['description']);
        $title = $this->escapeICSString($event['title']);
        $location = !empty($event['location']) ? $this->escapeICSString($event['location']) : '';
        
        // Build iCalendar content with proper line folding
        $ics = [];
        $ics[] = 'BEGIN:VCALENDAR';
        $ics[] = 'VERSION:2.0';
        $ics[] = $this->foldICSLine('PRODID:-//' . (defined('SITE_NAME') ? SITE_NAME : 'IBC-Intra') . '//NONSGML Event Calendar//EN');
        $ics[] = 'CALSCALE:GREGORIAN';
        $ics[] = 'METHOD:PUBLISH';
        $ics[] = 'X-WR-TIMEZONE:Europe/Berlin';
        $ics[] = 'BEGIN:VEVENT';
        $ics[] = $this->foldICSLine('UID:' . $uid);
        $ics[] = $this->foldICSLine('DTSTAMP:' . $dtstamp);
        $ics[] = $this->foldICSLine('DTSTART:' . $dtstart);
        $ics[] = $this->foldICSLine('DTEND:' . $dtendFormatted);
        $ics[] = $this->foldICSLine('SUMMARY:' . $title);
        $ics[] = $this->foldICSLine('DESCRIPTION:' . $description);
        
        if (!empty($location)) {
            $ics[] = $this->foldICSLine('LOCATION:' . $location);
        }
        
        $ics[] = 'STATUS:CONFIRMED';
        $ics[] = 'SEQUENCE:0';
        
        // Add URL to event page if SITE_URL is defined
        if (defined('SITE_URL')) {
            $eventUrl = SITE_URL . '/index.php?page=events#event-' . $event['id'];
            $ics[] = $this->foldICSLine('URL:' . $eventUrl);
        }
        
        $ics[] = 'END:VEVENT';
        $ics[] = 'END:VCALENDAR';
        
        // Join with proper line endings (CRLF per RFC 5545)
        return implode("\r\n", $ics) . "\r\n";
    }
    
    /**
     * Generate Google Calendar URL for an event
     * Creates a link that opens Google Calendar with pre-filled event data
     * 
     * @param int $eventId Event ID
     * @return string|false Google Calendar URL or false on failure
     */
    public function generateGoogleCalendarUrl(int $eventId): string|false {
        // Get event from database
        $stmt = $this->pdo->prepare("
            SELECT 
                e.id, 
                e.title, 
                e.description, 
                e.event_date, 
                e.location
            FROM events e
            WHERE e.id = ?
        ");
        
        $stmt->execute([$eventId]);
        $event = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$event) {
            error_log("Event not found: ID {$eventId}");
            $this->lastFetchedEvent = null;
            return false;
        }
        
        // Store event data for potential reuse
        $this->lastFetchedEvent = $event;
        
        // Format dates for Google Calendar (YYYYMMDDTHHmmSS)
        $eventDateTime = new DateTime($event['event_date']);
        $startDate = $eventDateTime->format('Ymd\THis');
        
        // Set end time to 2 hours after start (default duration)
        $endDateTime = clone $eventDateTime;
        $endDateTime->modify('+2 hours');
        $endDate = $endDateTime->format('Ymd\THis');
        
        // Build Google Calendar URL parameters
        $params = [
            'action' => 'TEMPLATE',
            'text' => $event['title'],
            'dates' => $startDate . '/' . $endDate,
            'details' => strip_tags($event['description']),
        ];
        
        // Add location if available
        if (!empty($event['location'])) {
            $params['location'] = $event['location'];
        }
        
        // Add event URL in details if SITE_URL is defined
        if (defined('SITE_URL')) {
            $eventUrl = SITE_URL . '/index.php?page=events#event-' . $event['id'];
            $params['details'] .= "\n\nMehr Informationen: " . $eventUrl;
        }
        
        // Build URL
        $url = 'https://www.google.com/calendar/render?' . http_build_query($params);
        
        return $url;
    }
    
    /**
     * Generate iCalendar (.ics) file content for a specific helper slot
     * Public wrapper that retrieves slot details and generates ICS content
     * 
     * @param int $slotId Helper slot ID
     * @return string|false iCalendar content or false on failure
     */
    public function generateIcsForSlot(int $slotId): string|false {
        // Get helper slot information with event details in a single query
        $stmt = $this->pdo->prepare("
            SELECT 
                e.id as event_id,
                e.title as event_title,
                e.description as event_description,
                e.location,
                e.created_at,
                s.id as slot_id,
                s.task_name,
                s.start_time,
                s.end_time
            FROM event_helper_slots s
            INNER JOIN events e ON e.id = s.event_id
            WHERE s.id = ?
        ");
        
        $stmt->execute([$slotId]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$data) {
            error_log("Helper slot not found: Slot ID {$slotId}");
            return false;
        }
        
        // Store event data for potential reuse
        $this->lastFetchedEvent = [
            'id' => $data['event_id'],
            'title' => $data['event_title'],
            'description' => $data['event_description'],
            'location' => $data['location']
        ];
        
        // Generate unique identifier for the helper slot
        $uid = 'helper-slot-' . $data['slot_id'] . '@';
        if (defined('SITE_URL')) {
            $parsedUrl = parse_url(SITE_URL);
            if ($parsedUrl !== false && isset($parsedUrl['host'])) {
                $uid .= $parsedUrl['host'];
            } else {
                $uid .= 'localhost';
            }
        } else {
            $uid .= 'localhost';
        }
        
        // Format dates in iCalendar format (YYYYMMDDTHHMMSS)
        $startDateTime = new DateTime($data['start_time']);
        $dtstart = $startDateTime->format('Ymd\THis');
        
        $endDateTime = new DateTime($data['end_time']);
        $dtend = $endDateTime->format('Ymd\THis');
        
        // Format created timestamp
        $createdAt = new DateTime($data['created_at']);
        $dtstamp = $createdAt->format('Ymd\THis\Z');
        
        // Build title and description for the helper slot
        $title = $data['event_title'] . ' - ' . $data['task_name'] . ' (Helfer-Einsatz)';
        $description = 'Helfer-Einsatz: ' . $data['task_name'] . '\n\n' . $data['event_description'];
        
        // Escape special characters
        $title = $this->escapeICSString($title);
        $description = $this->escapeICSString($description);
        $location = !empty($data['location']) ? $this->escapeICSString($data['location']) : '';
        
        // Build iCalendar content with proper line folding
        $ics = [];
        $ics[] = 'BEGIN:VCALENDAR';
        $ics[] = 'VERSION:2.0';
        $ics[] = $this->foldICSLine('PRODID:-//' . (defined('SITE_NAME') ? SITE_NAME : 'IBC-Intra') . '//NONSGML Event Calendar//EN');
        $ics[] = 'CALSCALE:GREGORIAN';
        $ics[] = 'METHOD:PUBLISH';
        $ics[] = 'X-WR-TIMEZONE:Europe/Berlin';
        $ics[] = 'BEGIN:VEVENT';
        $ics[] = $this->foldICSLine('UID:' . $uid);
        $ics[] = $this->foldICSLine('DTSTAMP:' . $dtstamp);
        $ics[] = $this->foldICSLine('DTSTART:' . $dtstart);
        $ics[] = $this->foldICSLine('DTEND:' . $dtend);
        $ics[] = $this->foldICSLine('SUMMARY:' . $title);
        $ics[] = $this->foldICSLine('DESCRIPTION:' . $description);
        
        if (!empty($location)) {
            $ics[] = $this->foldICSLine('LOCATION:' . $location);
        }
        
        $ics[] = 'STATUS:CONFIRMED';
        $ics[] = 'SEQUENCE:0';
        
        // Add URL to event page if SITE_URL is defined
        if (defined('SITE_URL')) {
            $eventUrl = SITE_URL . '/index.php?page=events#event-' . $data['event_id'];
            $ics[] = $this->foldICSLine('URL:' . $eventUrl);
        }
        
        $ics[] = 'END:VEVENT';
        $ics[] = 'END:VCALENDAR';
        
        // Join with proper line endings (CRLF per RFC 5545)
        return implode("\r\n", $ics) . "\r\n";
    }
    
    /**
     * Escape special characters for iCalendar format
     * Handles commas, semicolons, backslashes, and newlines
     * 
     * @param string $string String to escape
     * @return string Escaped string
     */
    private function escapeICSString(string $string): string {
        // Strip HTML tags
        $string = strip_tags($string);
        
        // Escape backslashes first (must be done before other escaping)
        $string = str_replace('\\', '\\\\', $string);
        
        // Escape special characters
        $string = str_replace([',', ';'], ['\\,', '\\;'], $string);
        
        // Convert newlines to \n
        $string = str_replace(["\r\n", "\n", "\r"], '\\n', $string);
        
        return $string;
    }
    
    /**
     * Generate iCalendar (.ics) file content for a helper slot
     * Uses exact helper slot times instead of full event times
     * 
     * @param int $eventId Event ID
     * @param int $slotId Helper slot ID
     * @return string|false iCalendar content or false on failure
     */
    private function generateHelperSlotICS(int $eventId, int $slotId): string|false {
        // Get event and helper slot information
        $stmt = $this->pdo->prepare("
            SELECT 
                e.id as event_id,
                e.title as event_title,
                e.description as event_description,
                e.location,
                e.created_at,
                s.id as slot_id,
                s.task_name,
                s.start_time,
                s.end_time
            FROM events e
            INNER JOIN event_helper_slots s ON e.id = s.event_id
            WHERE e.id = ? AND s.id = ?
        ");
        
        $stmt->execute([$eventId, $slotId]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$data) {
            error_log("Event or helper slot not found: Event ID {$eventId}, Slot ID {$slotId}");
            return false;
        }
        
        // Store event data for potential reuse
        $this->lastFetchedEvent = [
            'id' => $data['event_id'],
            'title' => $data['event_title'],
            'description' => $data['event_description'],
            'location' => $data['location']
        ];
        
        // Generate unique identifier for the helper slot
        $uid = 'helper-slot-' . $data['slot_id'] . '@';
        if (defined('SITE_URL')) {
            $parsedUrl = parse_url(SITE_URL);
            if ($parsedUrl !== false && isset($parsedUrl['host'])) {
                $uid .= $parsedUrl['host'];
            } else {
                $uid .= 'localhost';
            }
        } else {
            $uid .= 'localhost';
        }
        
        // Format dates in iCalendar format (YYYYMMDDTHHMMSS)
        $startDateTime = new DateTime($data['start_time']);
        $dtstart = $startDateTime->format('Ymd\THis');
        
        $endDateTime = new DateTime($data['end_time']);
        $dtend = $endDateTime->format('Ymd\THis');
        
        // Format created timestamp
        $createdAt = new DateTime($data['created_at']);
        $dtstamp = $createdAt->format('Ymd\THis\Z');
        
        // Build title and description for the helper slot
        $title = $data['event_title'] . ' - ' . $data['task_name'] . ' (Helfer-Einsatz)';
        $description = 'Helfer-Einsatz: ' . $data['task_name'] . '\n\n' . $data['event_description'];
        
        // Escape special characters
        $title = $this->escapeICSString($title);
        $description = $this->escapeICSString($description);
        $location = !empty($data['location']) ? $this->escapeICSString($data['location']) : '';
        
        // Build iCalendar content with proper line folding
        $ics = [];
        $ics[] = 'BEGIN:VCALENDAR';
        $ics[] = 'VERSION:2.0';
        $ics[] = $this->foldICSLine('PRODID:-//' . (defined('SITE_NAME') ? SITE_NAME : 'IBC-Intra') . '//NONSGML Event Calendar//EN');
        $ics[] = 'CALSCALE:GREGORIAN';
        $ics[] = 'METHOD:PUBLISH';
        $ics[] = 'X-WR-TIMEZONE:Europe/Berlin';
        $ics[] = 'BEGIN:VEVENT';
        $ics[] = $this->foldICSLine('UID:' . $uid);
        $ics[] = $this->foldICSLine('DTSTAMP:' . $dtstamp);
        $ics[] = $this->foldICSLine('DTSTART:' . $dtstart);
        $ics[] = $this->foldICSLine('DTEND:' . $dtend);
        $ics[] = $this->foldICSLine('SUMMARY:' . $title);
        $ics[] = $this->foldICSLine('DESCRIPTION:' . $description);
        
        if (!empty($location)) {
            $ics[] = $this->foldICSLine('LOCATION:' . $location);
        }
        
        $ics[] = 'STATUS:CONFIRMED';
        $ics[] = 'SEQUENCE:0';
        
        // Add URL to event page if SITE_URL is defined
        if (defined('SITE_URL')) {
            $eventUrl = SITE_URL . '/index.php?page=events#event-' . $data['event_id'];
            $ics[] = $this->foldICSLine('URL:' . $eventUrl);
        }
        
        $ics[] = 'END:VEVENT';
        $ics[] = 'END:VCALENDAR';
        
        // Join with proper line endings (CRLF per RFC 5545)
        return implode("\r\n", $ics) . "\r\n";
    }
    
    /**
     * Fold an iCalendar property line according to RFC 5545 (max 75 octets per line)
     * Continuation lines start with a single space
     * 
     * @param string $line Complete iCalendar property line to fold (e.g., "SUMMARY:Long text")
     * @return string Folded line with proper CRLF continuation
     */
    private function foldICSLine(string $line): string {
        $maxLength = 75;
        
        if (strlen($line) <= $maxLength) {
            return $line;
        }
        
        $result = '';
        $remaining = $line;
        
        // First line - take full 75 characters
        $result .= substr($remaining, 0, $maxLength);
        $remaining = substr($remaining, $maxLength);
        
        // Continuation lines - take 74 characters (1 space + 74 chars)
        while (strlen($remaining) > 0) {
            $result .= "\r\n " . substr($remaining, 0, $maxLength - 1);
            $remaining = substr($remaining, $maxLength - 1);
        }
        
        return $result;
    }
}
