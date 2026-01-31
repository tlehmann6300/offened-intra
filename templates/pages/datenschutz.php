<?php
// Namen aus JSON laden - using absolute path from template location
$teamDataPath = dirname(__DIR__, 2) . '/assets/data/team_data.json';
$team_data = null;

if (file_exists($teamDataPath)) {
    $team_json = file_get_contents($teamDataPath);
    if ($team_json !== false) {
        $team_data = json_decode($team_json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('Datenschutz: JSON decode error - ' . json_last_error_msg());
            $team_data = null;
        }
    } else {
        error_log('Datenschutz: Failed to read team data file');
    }
} else {
    error_log('Datenschutz: Team data file not found at ' . $teamDataPath);
}

$vorstaende = [];
if ($team_data && isset($team_data['vorstand']) && is_array($team_data['vorstand'])) {
    foreach ($team_data['vorstand'] as $person) {
        if (isset($person['name'])) {
            $vorstaende[] = htmlspecialchars($person['name']);
        }
    }
}
$verantwortlich = !empty($vorstaende) ? implode(", ", $vorstaende) : "Der Vorstand der IBC";
?>

<main>
    <!-- Hero Section -->
    <section class="page-hero-section">
        <div class="container">
            <h1 class="ibc-heading text-center">Datenschutzerklärung</h1>
            <p class="ibc-lead text-center">
                Ihre Privatsphäre ist uns wichtig. Hier erfahren Sie, wie wir Ihre Daten schützen.
            </p>
        </div>
    </section>

    <!-- Datenschutz Content -->
    <section class="py-5">
        <div class="container">
            <div class="glass-card p-5 h-100">
                
                <!-- 1. Verantwortlicher im Sinne der DSGVO -->
                <h2 class="mb-4">1. Verantwortlicher</h2>
                <p>Verantwortlich für die Datenverarbeitung auf dieser Plattform im Sinne der DSGVO sind die aktuellen Vorstände: <br>
                <strong><?php echo $verantwortlich; ?></strong></p>
                
                <!-- TODO: Replace placeholder text below with full GDPR-compliant privacy policy content before production deployment -->
                <p><em>(Hier folgen deine Standard-DSGVO Texte...)</em></p>

                <!-- 2. Allgemeine Hinweise -->
                <h2 class="mb-4">2. Allgemeine Hinweise zur Datenverarbeitung</h2>
                <p>
                    Die folgenden Hinweise geben einen einfachen Überblick darüber, was mit Ihren personenbezogenen Daten 
                    passiert, wenn Sie diese Website besuchen. Personenbezogene Daten sind alle Daten, mit denen Sie 
                    persönlich identifiziert werden können.
                </p>

                <!-- 3. Datenerfassung auf dieser Website -->
                <h2 class="mb-4">3. Datenerfassung auf dieser Website</h2>
                
                <h3 class="mb-3">Wer ist verantwortlich für die Datenerfassung?</h3>
                <p>
                    Die Datenverarbeitung auf dieser Website erfolgt durch den Websitebetreiber. Dessen Kontaktdaten 
                    können Sie dem Abschnitt „Verantwortlicher im Sinne der DSGVO" in dieser Datenschutzerklärung entnehmen.
                </p>

                <h3 class="mb-3">Wie erfassen wir Ihre Daten?</h3>
                <p>
                    Ihre Daten werden zum einen dadurch erhoben, dass Sie uns diese mitteilen. Hierbei kann es sich z.B. 
                    um Daten handeln, die Sie in ein Kontaktformular eingeben.
                </p>
                <p>
                    Andere Daten werden automatisch oder nach Ihrer Einwilligung beim Besuch der Website durch unsere 
                    IT-Systeme erfasst. Das sind vor allem technische Daten (z.B. Internetbrowser, Betriebssystem oder 
                    Uhrzeit des Seitenaufrufs). Die Erfassung dieser Daten erfolgt automatisch, sobald Sie diese Website betreten.
                </p>

                <h3 class="mb-3">Wofür nutzen wir Ihre Daten?</h3>
                <p>
                    Ein Teil der Daten wird erhoben, um eine fehlerfreie Bereitstellung der Website zu gewährleisten. 
                    Andere Daten können zur Analyse Ihres Nutzerverhaltens verwendet werden.
                </p>

                <h3 class="mb-3">Welche Rechte haben Sie bezüglich Ihrer Daten?</h3>
                <p>
                    Sie haben jederzeit das Recht, unentgeltlich Auskunft über Herkunft, Empfänger und Zweck Ihrer 
                    gespeicherten personenbezogenen Daten zu erhalten. Sie haben außerdem ein Recht, die Berichtigung 
                    oder Löschung dieser Daten zu verlangen. Wenn Sie eine Einwilligung zur Datenverarbeitung erteilt 
                    haben, können Sie diese Einwilligung jederzeit für die Zukunft widerrufen.
                </p>

                <!-- 4. Cookies -->
                <h2 class="mb-4">4. Cookies</h2>
                <p>
                    Diese Website verwendet Cookies. Das sind kleine Textdateien, die auf Ihrem Endgerät gespeichert 
                    werden und die Ihr Browser speichert. Cookies richten auf Ihrem Rechner keinen Schaden an und 
                    enthalten keine Viren.
                </p>
                <p>
                    Wir verwenden Cookies, um unser Angebot nutzerfreundlich zu gestalten. Einige Cookies bleiben auf 
                    Ihrem Endgerät gespeichert, bis Sie diese löschen. Sie ermöglichen es uns, Ihren Browser beim 
                    nächsten Besuch wiederzuerkennen.
                </p>
                <p>
                    Sie können Ihren Browser so einstellen, dass Sie über das Setzen von Cookies informiert werden und 
                    Cookies nur im Einzelfall erlauben, die Annahme von Cookies für bestimmte Fälle oder generell 
                    ausschließen sowie das automatische Löschen der Cookies beim Schließen des Browsers aktivieren.
                </p>

                <!-- 5. Server-Log-Dateien -->
                <h2 class="mb-4">5. Server-Log-Dateien</h2>
                <p>
                    Der Provider der Seiten erhebt und speichert automatisch Informationen in so genannten Server-Log-Dateien, 
                    die Ihr Browser automatisch an uns übermittelt. Dies sind:
                </p>
                <ul>
                    <li>Browsertyp und Browserversion</li>
                    <li>Verwendetes Betriebssystem</li>
                    <li>Referrer URL</li>
                    <li>Hostname des zugreifenden Rechners</li>
                    <li>Uhrzeit der Serveranfrage</li>
                    <li>IP-Adresse</li>
                </ul>
                <p>
                    Eine Zusammenführung dieser Daten mit anderen Datenquellen wird nicht vorgenommen. Die Erfassung 
                    dieser Daten erfolgt auf Grundlage von Art. 6 Abs. 1 lit. f DSGVO. Der Websitebetreiber hat ein 
                    berechtigtes Interesse an der technisch fehlerfreien Darstellung und der Optimierung seiner Website.
                </p>

                <!-- 6. Kontaktformular -->
                <h2 class="mb-4">6. Kontaktformular</h2>
                <p>
                    Wenn Sie uns per Kontaktformular Anfragen zukommen lassen, werden Ihre Angaben aus dem Anfrageformular 
                    inklusive der von Ihnen dort angegebenen Kontaktdaten zwecks Bearbeitung der Anfrage und für den Fall 
                    von Anschlussfragen bei uns gespeichert. Diese Daten geben wir nicht ohne Ihre Einwilligung weiter.
                </p>
                <p>
                    Die Verarbeitung dieser Daten erfolgt auf Grundlage von Art. 6 Abs. 1 lit. b DSGVO, sofern Ihre 
                    Anfrage mit der Erfüllung eines Vertrags zusammenhängt oder zur Durchführung vorvertraglicher 
                    Maßnahmen erforderlich ist. In allen übrigen Fällen beruht die Verarbeitung auf unserem berechtigten 
                    Interesse an der effektiven Bearbeitung der an uns gerichteten Anfragen (Art. 6 Abs. 1 lit. f DSGVO).
                </p>

                <!-- 7. Alumni-Profile und Netzwerk -->
                <h2 class="mb-4">7. Alumni-Profile und Netzwerk</h2>
                <h3 class="mb-3">Datenerfassung bei Alumni-Profilen</h3>
                <p>
                    Als Teil unseres Alumni-Netzwerks haben Sie die Möglichkeit, ein persönliches Profil anzulegen. 
                    Dabei werden folgende personenbezogene Daten erfasst:
                </p>
                <ul>
                    <li>Vor- und Nachname</li>
                    <li>E-Mail-Adresse</li>
                    <li>Telefonnummer (optional)</li>
                    <li>Aktueller Arbeitgeber und Position (optional)</li>
                    <li>Standort (optional)</li>
                    <li>Branche (optional)</li>
                    <li>Abschlussjahr</li>
                    <li>Profilbild (optional)</li>
                    <li>Biografische Angaben (optional)</li>
                    <li>LinkedIn-Profil (optional)</li>
                </ul>
                
                <h3 class="mb-3">Zweck und Rechtsgrundlage der Verarbeitung</h3>
                <p>
                    Die Verarbeitung Ihrer Alumni-Profildaten erfolgt auf Grundlage Ihrer ausdrücklichen Einwilligung 
                    (Art. 6 Abs. 1 lit. a DSGVO) zum Zweck der:
                </p>
                <ul>
                    <li>Vernetzung mit anderen Alumni</li>
                    <li>Bereitstellung von Alumni-spezifischen Informationen und Veranstaltungen</li>
                    <li>Förderung des beruflichen Netzwerks</li>
                    <li>Pflege der Alumni-Community</li>
                </ul>
                
                <h3 class="mb-3">Sichtbarkeit und Kontrolle Ihrer Daten</h3>
                <p>
                    Sie haben jederzeit die volle Kontrolle über Ihre Alumni-Profildaten über Ihr persönliches Profil 
                    in der Alumni-Datenbank:
                </p>
                <ul>
                    <li><strong>Veröffentlichungsstatus:</strong> Sie können in Ihren Profileinstellungen selbst entscheiden, ob Ihr Profil für andere Alumni sichtbar ist oder nicht.</li>
                    <li><strong>Datenbearbeitung:</strong> Sie können über den "Bearbeiten"-Button in Ihrem Profil Ihre Profildaten jederzeit selbst bearbeiten, ergänzen oder einzelne Angaben entfernen.</li>
                    <li><strong>Profillöschung:</strong> Sie können Ihr Alumni-Profil jederzeit vollständig über Ihre Profileinstellungen oder durch Kontaktaufnahme mit dem Vorstand löschen.</li>
                </ul>
                <p>
                    <em>Hinweis: Zugriff auf Ihre Profileinstellungen erhalten Sie über die Alumni-Datenbank im Bereich 
                    "Alumni" des Intranets.</em>
                </p>
                
                <h3 class="mb-3">Weitergabe von Alumni-Daten</h3>
                <p>
                    Ihre Alumni-Profildaten werden nur innerhalb des Alumni-Netzwerks sichtbar gemacht, sofern Sie Ihr 
                    Profil auf "veröffentlicht" gesetzt haben. Eine Weitergabe an Dritte außerhalb des Alumni-Netzwerks 
                    erfolgt nicht ohne Ihre ausdrückliche Einwilligung.
                </p>
                
                <h3 class="mb-3">Speicherdauer</h3>
                <p>
                    Ihre Alumni-Profildaten werden gespeichert, solange Sie Ihr Profil aktiv halten. Bei Löschung Ihres 
                    Profils werden alle personenbezogenen Daten unverzüglich gelöscht, sofern keine gesetzlichen 
                    Aufbewahrungspflichten entgegenstehen.
                </p>

                <!-- 8. Newsletter und E-Mail-Abonnements -->
                <h2 class="mb-4">8. Newsletter und E-Mail-Abonnements</h2>
                <h3 class="mb-3">Newsletter-Versand</h3>
                <p>
                    Wenn Sie den auf der Website angebotenen Newsletter beziehen möchten, benötigen wir von Ihnen 
                    eine E-Mail-Adresse sowie Informationen, welche uns die Überprüfung gestatten, dass Sie der 
                    Inhaber der angegebenen E-Mail-Adresse sind und mit dem Empfang des Newsletters einverstanden sind.
                </p>
                
                <h3 class="mb-3">Rechtsgrundlage und Zweck</h3>
                <p>
                    Die Verarbeitung Ihrer E-Mail-Adresse zum Versand des Newsletters erfolgt auf Grundlage Ihrer 
                    Einwilligung (Art. 6 Abs. 1 lit. a DSGVO). Der Newsletter dient dazu, Sie über:
                </p>
                <ul>
                    <li>Aktuelle Neuigkeiten aus dem IBC</li>
                    <li>Bevorstehende Veranstaltungen</li>
                    <li>Wichtige Ankündigungen</li>
                    <li>Alumni-relevante Informationen</li>
                </ul>
                <p>zu informieren.</p>
                
                <h3 class="mb-3">Widerrufsrecht für E-Mail-Abonnements</h3>
                <p>
                    Sie haben jederzeit das Recht, Ihre Einwilligung zum Empfang des Newsletters zu widerrufen. 
                    Der Widerruf kann auf folgende Weisen erfolgen:
                </p>
                <ul>
                    <li><strong>Abmeldelink:</strong> Jeder Newsletter enthält am Ende einen Abmeldelink, über den Sie sich mit einem Klick abmelden können.</li>
                    <li><strong>E-Mail:</strong> Sie können uns eine formlose E-Mail an die im Impressum angegebene E-Mail-Adresse senden.</li>
                    <li><strong>Einstellungen:</strong> In Ihren Kontoeinstellungen im Bereich "Einstellungen" des Intranets können Sie Newsletter-Abonnements verwalten und jederzeit abmelden.</li>
                </ul>
                <p>
                    Der Widerruf ist jederzeit möglich und kostenlos. Die Rechtmäßigkeit der bereits erfolgten 
                    Datenverarbeitung bleibt vom Widerruf unberührt. Nach dem Widerruf werden Ihre Daten nicht mehr 
                    für den Newsletter-Versand verwendet und unverzüglich aus dem Verteiler gelöscht.
                </p>
                <p>
                    <em>Hinweis: Ihre Kontoeinstellungen erreichen Sie über das Benutzer-Menü in der Navigationsleiste 
                    unter "Einstellungen".</em>
                </p>
                
                <h3 class="mb-3">Keine Weitergabe an Dritte</h3>
                <p>
                    Ihre Newsletter-E-Mail-Adresse wird nicht an Dritte weitergegeben. Sie wird ausschließlich für 
                    den Versand unseres eigenen Newsletters verwendet.
                </p>

                <!-- 9. Ihre Rechte -->
                <h2 class="mb-4">9. Ihre Rechte</h2>
                <p>
                    Sie haben folgende Rechte bezüglich Ihrer personenbezogenen Daten:
                </p>
                <ul>
                    <li><strong>Recht auf Auskunft:</strong> Sie können Auskunft über Ihre gespeicherten Daten verlangen.</li>
                    <li><strong>Recht auf Berichtigung:</strong> Sie können die Korrektur unrichtiger Daten verlangen.</li>
                    <li><strong>Recht auf Löschung:</strong> Sie können die Löschung Ihrer Daten verlangen.</li>
                    <li><strong>Recht auf Einschränkung:</strong> Sie können die Einschränkung der Verarbeitung verlangen.</li>
                    <li><strong>Recht auf Datenübertragbarkeit:</strong> Sie können eine Kopie Ihrer Daten in einem strukturierten Format erhalten.</li>
                    <li><strong>Widerspruchsrecht:</strong> Sie können der Verarbeitung Ihrer Daten widersprechen.</li>
                    <li><strong>Recht auf Widerruf:</strong> Sie können erteilte Einwilligungen jederzeit mit Wirkung für die Zukunft widerrufen.</li>
                </ul>
                <p>
                    Zur Ausübung Ihrer Rechte wenden Sie sich bitte an die im Impressum angegebene Kontaktadresse. 
                    Sie haben zudem das Recht, sich bei einer Datenschutz-Aufsichtsbehörde über die Verarbeitung 
                    Ihrer personenbezogenen Daten zu beschweren.
                </p>

                <!-- 10. SSL-Verschlüsselung -->
                <h2 class="mb-4">10. SSL- bzw. TLS-Verschlüsselung</h2>
                <p>
                    Diese Seite nutzt aus Sicherheitsgründen und zum Schutz der Übertragung vertraulicher Inhalte eine 
                    SSL- bzw. TLS-Verschlüsselung. Eine verschlüsselte Verbindung erkennen Sie daran, dass die Adresszeile 
                    des Browsers von „http://" auf „https://" wechselt und an dem Schloss-Symbol in Ihrer Browserzeile.
                </p>

                <!-- 11. Änderungen -->
                <h2 class="mb-4">11. Änderungen dieser Datenschutzerklärung</h2>
                <p>
                    Wir behalten uns vor, diese Datenschutzerklärung anzupassen, damit sie stets den aktuellen 
                    rechtlichen Anforderungen entspricht oder um Änderungen unserer Leistungen in der Datenschutzerklärung 
                    umzusetzen. Für Ihren erneuten Besuch gilt dann die neue Datenschutzerklärung.
                </p>

                <!-- Stand -->
                <div class="mt-5 pt-4 border-top">
                    <p class="text-muted small">
                        <strong>Stand:</strong> <?php echo date('d.m.Y'); ?>
                    </p>
                </div>

            </div>
        </div>
    </section>
</main>
