<!-- Global Search Component -->
<!-- Clean, responsive search component for navbar -->
<?php $instanceId = $searchInstance ?? 'default'; ?>
<div class="position-relative global-search-wrapper w-100" data-search-instance="<?= $instanceId ?>">
    <div class="input-group navbar-search-input-group">
        <span class="input-group-text bg-transparent border-end-0">
            <i class="fas fa-search text-muted"></i>
        </span>
        <input type="text" 
               class="form-control global-search-input border-start-0 border-end-0 ps-0" 
               data-search-instance="<?= $instanceId ?>"
               placeholder="Suche nach Personen, Events, Inventar..."
               autocomplete="off"
               aria-label="Globale Suche">
        <button class="btn btn-outline-light global-search-submit" 
                type="button"
                data-search-instance="<?= $instanceId ?>"
                aria-label="Suche starten">
            <i class="fas fa-arrow-right"></i>
        </button>
    </div>
    <div class="dropdown-menu global-search-results w-100" 
         data-search-instance="<?= $instanceId ?>"></div>
</div>
