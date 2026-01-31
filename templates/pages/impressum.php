<?php
// Namen aus JSON laden - using absolute path from template location
$teamDataPath = dirname(__DIR__, 2) . '/assets/data/team_data.json';
$team_data = null;

if (file_exists($teamDataPath)) {
    $team_json = file_get_contents($teamDataPath);
    if ($team_json !== false) {
        $team_data = json_decode($team_json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('Impressum: JSON decode error - ' . json_last_error_msg());
            $team_data = null;
        }
    } else {
        error_log('Impressum: Failed to read team data file');
    }
} else {
    error_log('Impressum: Team data file not found at ' . $teamDataPath);
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
            <h1 class="ibc-heading text-center">Impressum</h1>
            <p class="ibc-lead text-center">
                Angaben gemäß § 5 TMG
            </p>
        </div>
    </section>

    <!-- Impressum Content -->
    <section class="py-5">
        <div class="container">
            <div class="glass-card p-5 h-100">
                
                <!-- Angaben gemäß § 5 TMG -->
                <h2 class="mb-4">Angaben gemäß § 5 TMG</h2>
                <p>
                    <strong>IBC - International Business Club e.V.</strong><br>
                    <!-- TODO: Replace placeholder address with actual organization address -->
                    <em>[Adresse des Vereins]</em><br>
                    <em>[PLZ Ort]</em>
                </p>
                
                <!-- Vertreten durch -->
                <h2 class="mb-4">Vertreten durch</h2>
                <p>
                    <strong>Der Vorstand:</strong><br>
                    <?php echo $verantwortlich; ?>
                </p>

                <!-- Kontakt -->
                <h2 class="mb-4">Kontakt</h2>
                <p>
                    <!-- TODO: Replace placeholder contact with actual organization contact -->
                    E-Mail: <em>[E-Mail-Adresse des Vereins]</em><br>
                    <em>[Optional: Telefonnummer]</em>
                </p>

                <!-- Vereinsregister -->
                <h2 class="mb-4">Registereintrag</h2>
                <p>
                    <!-- TODO: Replace placeholder with actual registration information if available -->
                    <em>Eintragung im Vereinsregister:<br>
                    Registergericht: [Amtsgericht]<br>
                    Registernummer: [VR-Nummer]</em>
                </p>

                <!-- Verantwortlich für den Inhalt -->
                <h2 class="mb-4">Verantwortlich für den Inhalt nach § 55 Abs. 2 RStV</h2>
                <p>
                    <?php echo $verantwortlich; ?><br>
                    <em>[Adresse]</em>
                </p>

                <!-- Haftungsausschluss -->
                <h2 class="mb-4">Haftungsausschluss</h2>
                
                <h3 class="mb-3">Haftung für Inhalte</h3>
                <p>
                    Als Diensteanbieter sind wir gemäß § 7 Abs.1 TMG für eigene Inhalte auf diesen Seiten nach den 
                    allgemeinen Gesetzen verantwortlich. Nach §§ 8 bis 10 TMG sind wir als Diensteanbieter jedoch nicht 
                    verpflichtet, übermittelte oder gespeicherte fremde Informationen zu überwachen oder nach Umständen 
                    zu forschen, die auf eine rechtswidrige Tätigkeit hinweisen.
                </p>
                <p>
                    Verpflichtungen zur Entfernung oder Sperrung der Nutzung von Informationen nach den allgemeinen 
                    Gesetzen bleiben hiervon unberührt. Eine diesbezügliche Haftung ist jedoch erst ab dem Zeitpunkt 
                    der Kenntnis einer konkreten Rechtsverletzung möglich. Bei Bekanntwerden von entsprechenden 
                    Rechtsverletzungen werden wir diese Inhalte umgehend entfernen.
                </p>

                <h3 class="mb-3">Haftung für Links</h3>
                <p>
                    Unser Angebot enthält Links zu externen Websites Dritter, auf deren Inhalte wir keinen Einfluss haben. 
                    Deshalb können wir für diese fremden Inhalte auch keine Gewähr übernehmen. Für die Inhalte der 
                    verlinkten Seiten ist stets der jeweilige Anbieter oder Betreiber der Seiten verantwortlich. Die 
                    verlinkten Seiten wurden zum Zeitpunkt der Verlinkung auf mögliche Rechtsverstöße überprüft. 
                    Rechtswidrige Inhalte waren zum Zeitpunkt der Verlinkung nicht erkennbar.
                </p>
                <p>
                    Eine permanente inhaltliche Kontrolle der verlinkten Seiten ist jedoch ohne konkrete Anhaltspunkte 
                    einer Rechtsverletzung nicht zumutbar. Bei Bekanntwerden von Rechtsverletzungen werden wir derartige 
                    Links umgehend entfernen.
                </p>

                <h3 class="mb-3">Urheberrecht</h3>
                <p>
                    Die durch die Seitenbetreiber erstellten Inhalte und Werke auf diesen Seiten unterliegen dem 
                    deutschen Urheberrecht. Die Vervielfältigung, Bearbeitung, Verbreitung und jede Art der Verwertung 
                    außerhalb der Grenzen des Urheberrechtes bedürfen der schriftlichen Zustimmung des jeweiligen Autors 
                    bzw. Erstellers. Downloads und Kopien dieser Seite sind nur für den privaten, nicht kommerziellen 
                    Gebrauch gestattet.
                </p>
                <p>
                    Soweit die Inhalte auf dieser Seite nicht vom Betreiber erstellt wurden, werden die Urheberrechte 
                    Dritter beachtet. Insbesondere werden Inhalte Dritter als solche gekennzeichnet. Sollten Sie trotzdem 
                    auf eine Urheberrechtsverletzung aufmerksam werden, bitten wir um einen entsprechenden Hinweis. 
                    Bei Bekanntwerden von Rechtsverletzungen werden wir derartige Inhalte umgehend entfernen.
                </p>

                <!-- Hinweis -->
                <div class="alert alert-info mt-4">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong>Hinweis:</strong> Dieses Impressum enthält Platzhalter und muss vor dem produktiven Einsatz 
                    mit den tatsächlichen Angaben des Vereins vervollständigt werden.
                </div>

            </div>
        </div>
    </section>
</main>
