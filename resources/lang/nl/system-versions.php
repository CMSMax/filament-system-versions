<?php

return [
    'navigation' => [
        'label' => 'Systeemversies',
        'group' => 'Instellingen',
    ],
    'actions' => [
        'check_now' => [
            'label' => 'Nu controleren',
            'modal_heading' => 'Dependencyversies nu controleren?',
            'modal_description' => 'Hiermee worden de geconfigureerde Composer-repositories gecontroleerd en wordt de opgeslagen Composer-snapshot vernieuwd. npm-packages worden rechtstreeks uit het lockbestand gelezen.',
            'modal_submit' => 'Nu controleren',
            'success_title' => 'Dependencyversies bijgewerkt',
            'success_body' => 'De package-informatie op deze pagina is nu actueel.',
            'already_running_title' => 'Controle wordt al uitgevoerd',
            'already_running_body' => 'Er wordt al een dependencycontrole uitgevoerd. Probeer het over een paar minuten opnieuw.',
            'failure_title' => 'Dependencyversies konden niet worden gecontroleerd',
            'failure_body' => 'De bestaande snapshot is behouden. Controleer de applicatielogs en probeer het opnieuw.',
        ],
    ],
    'not_available' => 'Niet beschikbaar',
    'statuses' => [
        'up-to-date' => 'Actueel',
        'semver-safe-update' => 'Update beschikbaar',
        'update-possible' => 'Grote update beschikbaar',
    ],
    'groups' => [
        'direct-runtime' => 'Directe runtime-packages',
        'direct-development' => 'Directe ontwikkelpackages',
        'direct-optional' => 'Directe optionele packages',
        'direct-peer' => 'Directe peer-packages',
        'transitive-runtime' => 'Transitieve runtime-packages',
        'transitive-development' => 'Transitieve ontwikkelpackages',
        'transitive-optional' => 'Transitieve optionele packages',
        'transitive-peer' => 'Transitieve peer-packages',
        'unclassified' => 'Niet-geclassificeerde packages',
    ],
    'widgets' => [
        'dependency' => [
            'heading' => 'Composer-packages',
            'description' => 'Elk geïnstalleerd Composer-package, gegroepeerd op relatie en applicatiebereik',
            'abandoned' => 'Niet meer onderhouden',
            'summary_label' => 'Samenvatting Composer-packages',
            'total' => 'geïnstalleerd',
            'updates' => 'updates',
            'to' => 'naar',
            'no_matches' => 'Geen packages die aan dit filter voldoen.',
            'no_data' => 'Nog geen dependency-gegevens. Voer het Artisan-commando dependency:versions uit om deze te verzamelen.',
            'missing_table' => 'De composer versions-tabel is nog niet gemigreerd. Publiceer en voer eerst de migraties van dit package uit.',
            'table' => [
                'name' => 'Naam',
                'version' => 'Versie',
                'status' => 'Status',
            ],
        ],
        'npm' => [
            'heading' => 'npm-packages',
            'description' => 'Elke opgeloste package-instantie uit package-lock.json, inclusief geneste versies',
            'unavailable' => [
                'missing' => 'Er is geen package-lock.json gevonden. Configureer het pad als dit project het lockbestand elders bewaart.',
                'invalid' => 'package-lock.json kon niet als geldig npm-lockbestand worden gelezen.',
                'unsupported' => 'Deze versie van package-lock.json wordt niet ondersteund. Genereer een lockbestand met npm 7 of nieuwer.',
            ],
            'summary_label' => 'Samenvatting npm-packages',
            'instances' => 'opgeloste instanties',
            'unique_versions' => 'unieke packageversies',
            'lockfile' => 'lockbestand',
            'table' => [
                'name' => 'Naam en lockbestandpad',
                'version' => 'Exacte versie',
            ],
        ],
        'system' => [
            'heading' => 'Systeemeigenschappen',
            'description' => 'Een samenvatting van de systeemomgeving',
            'details' => [
                'environment' => 'Omgeving',
                'timezone' => 'Tijdzone',
                'debug' => 'Debug-modus',
                'debug_enabled' => 'Ingeschakeld',
                'debug_disabled' => 'Uitgeschakeld',
            ],
        ],
        'stats' => [
            'not_installed' => 'Niet geïnstalleerd',
        ],
    ],
];
