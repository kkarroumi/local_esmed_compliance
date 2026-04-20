<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Chaînes de langue françaises pour local_esmed_compliance.
 *
 * @package    local_esmed_compliance
 * @copyright  2026 ESMED
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'ESMED Conformité';
$string['pluginname_desc'] = 'Boîte à outils de conformité réglementaire pour les organismes de formation français (Qualiopi, CPF, France Travail) : traçabilité des sessions certifiables, attestations d\'assiduité (article D.6353-4), bordereaux financeurs et archivage probant WORM.';

// Génériques.
$string['dashboard'] = 'Tableau de bord conformité';
$string['yes'] = 'Oui';
$string['no'] = 'Non';

// Tâches planifiées.
$string['task_session_timeout'] = 'Clore les sessions de conformité inactives';

// Modes de clôture de session (exposés dans les rapports).
$string['closure_logout'] = 'Déconnexion';
$string['closure_timeout'] = 'Timeout';
$string['closure_beacon'] = 'Navigateur fermé';
$string['closure_crash'] = 'Crash';
$string['closure_manual'] = 'Manuelle';

// Paramètres - Général.
$string['settings_general'] = 'Général';
$string['setting_heartbeat_interval'] = 'Intervalle de heartbeat (secondes)';
$string['setting_heartbeat_interval_desc'] = 'Fréquence à laquelle le navigateur envoie un signal de présence tant que l\'utilisateur est sur une page Moodle.';
$string['setting_session_timeout_minutes'] = 'Délai d\'inactivité avant clôture (minutes)';
$string['setting_session_timeout_minutes_desc'] = 'Les sessions ouvertes sans heartbeat depuis plus longtemps que cette durée sont clôturées automatiquement en mode « timeout ».';
$string['setting_activity_delta_cap_minutes'] = 'Plafond du delta inter-vues (minutes)';
$string['setting_activity_delta_cap_minutes_desc'] = 'Durée maximale comptabilisée entre deux consultations consécutives d\'un même module.';
$string['setting_retention_years'] = 'Durée de conservation (années)';
$string['setting_retention_years_desc'] = 'Durée pendant laquelle les documents scellés sont conservés avant de devenir éligibles à la purge.';
$string['setting_funder_default'] = 'Financeur par défaut';
$string['setting_funder_default_desc'] = 'Type de financeur présélectionné lors de la liaison d\'un nouveau cours. Laissez vide pour imposer un choix explicite.';
$string['funder_none'] = 'Aucun';
$string['funder_cpf'] = 'CPF (Mon Compte Formation)';
$string['funder_ft'] = 'France Travail';
$string['funder_opco'] = 'OPCO';
$string['funder_region'] = 'Région';
$string['funder_autre'] = 'Autre';

// Paramètres - Identité de l\'organisme.
$string['settings_branding'] = 'Identité OF';
$string['setting_org_logo'] = 'Logo de l\'organisme';
$string['setting_org_logo_desc'] = 'Logo imprimé en en-tête des attestations d\'assiduité et des bordereaux financeurs.';
$string['setting_org_legal_name'] = 'Raison sociale';
$string['setting_org_legal_name_desc'] = 'Dénomination légale de l\'organisme de formation.';
$string['setting_org_siret'] = 'SIRET';
$string['setting_org_siret_desc'] = 'Numéro SIRET à 14 chiffres.';
$string['setting_org_nda'] = 'Numéro de déclaration d\'activité (NDA)';
$string['setting_org_nda_desc'] = 'Numéro de la déclaration d\'activité enregistrée auprès de la DREETS.';
$string['setting_org_address'] = 'Adresse postale';
$string['setting_org_address_desc'] = 'Adresse du siège social imprimée sur les documents légaux.';
$string['setting_org_signatory_name'] = 'Nom du signataire';
$string['setting_org_signatory_name_desc'] = 'Nom de la personne signant les attestations.';
$string['setting_org_signatory_role'] = 'Fonction du signataire';
$string['setting_org_signatory_role_desc'] = 'Fonction ou titre du signataire.';

// Paramètres - Archivage.
$string['settings_archive'] = 'Archivage';
$string['setting_archive_storage_adapter'] = 'Adaptateur de stockage des archives';
$string['setting_archive_storage_adapter_desc'] = 'Destination des documents scellés (système de fichiers local ou stockage objet S3).';
$string['adapter_local'] = 'Système de fichiers local';
$string['adapter_s3'] = 'Stockage objet S3';
$string['setting_archive_local_path'] = 'Chemin de l\'archivage local';
$string['setting_archive_local_path_desc'] = 'Chemin absolu hors racine web. Laissez vide pour utiliser le moodledata par défaut.';
$string['settings_archive_s3_heading'] = 'Identifiants S3';
$string['settings_archive_s3_heading_desc'] = 'Requis uniquement si l\'adaptateur est positionné sur S3. Utilisez un bucket avec Object Lock en mode compliance.';
$string['setting_s3_endpoint'] = 'URL d\'endpoint S3';
$string['setting_s3_endpoint_desc'] = 'Laissez vide pour AWS. À renseigner pour OVH, Scaleway ou tout fournisseur S3-compatible.';
$string['setting_s3_region'] = 'Région S3';
$string['setting_s3_region_desc'] = 'Identifiant de région AWS (ex. eu-west-3 pour Paris).';
$string['setting_s3_bucket'] = 'Bucket S3';
$string['setting_s3_bucket_desc'] = 'Nom du bucket dédié aux documents scellés.';
$string['setting_s3_access_key'] = 'Clé d\'accès S3';
$string['setting_s3_access_key_desc'] = 'Identifiant IAM autorisé à écrire dans le bucket.';
$string['setting_s3_secret_key'] = 'Clé secrète S3';
$string['setting_s3_secret_key_desc'] = 'Clé secrète IAM associée à la clé d\'accès ci-dessus.';

// Capacités.
$string['esmed_compliance:viewdashboard'] = 'Consulter le tableau de bord de conformité';
$string['esmed_compliance:generateattestation'] = 'Générer des attestations d\'assiduité';
$string['esmed_compliance:manageconfig'] = 'Gérer les liaisons financeurs et la configuration du plugin';
$string['esmed_compliance:viewownreports'] = 'Consulter ses propres rapports de conformité';
$string['esmed_compliance:exportfundedata'] = 'Exporter les bordereaux financeurs';
$string['esmed_compliance:managearchive'] = 'Accéder aux archives scellées';

// Métadonnées RGPD.
$string['privacy:metadata:local_esmed_sessions'] = 'Sessions certifiables utilisées comme preuves d\'assiduité au titre de l\'article D.6353-4 du Code du travail.';
$string['privacy:metadata:local_esmed_sessions:userid'] = 'Utilisateur Moodle dont la session est enregistrée.';
$string['privacy:metadata:local_esmed_sessions:courseid'] = 'Cours auquel la session est rattachée, le cas échéant.';
$string['privacy:metadata:local_esmed_sessions:session_start'] = 'Horodatage d\'ouverture de la session.';
$string['privacy:metadata:local_esmed_sessions:session_end'] = 'Horodatage de clôture de la session.';
$string['privacy:metadata:local_esmed_sessions:ip_address'] = 'Adresse IP depuis laquelle la session a été ouverte.';
$string['privacy:metadata:local_esmed_sessions:user_agent'] = 'User-agent du navigateur au moment de l\'ouverture.';
$string['privacy:metadata:local_esmed_sessions:duration_seconds'] = 'Durée effective de la session, en secondes.';
$string['privacy:metadata:local_esmed_sessions:closure_type'] = 'Mode de clôture de la session (déconnexion, timeout, beacon, crash, manuel).';

$string['privacy:metadata:local_esmed_activity_log'] = 'Temps passé et nombre de consultations agrégés par module de cours.';
$string['privacy:metadata:local_esmed_activity_log:userid'] = 'Utilisateur Moodle dont l\'activité modulaire est agrégée.';
$string['privacy:metadata:local_esmed_activity_log:courseid'] = 'Cours auquel le module appartient.';
$string['privacy:metadata:local_esmed_activity_log:cmid'] = 'Identifiant du module de cours.';
$string['privacy:metadata:local_esmed_activity_log:time_spent_seconds'] = 'Temps total passé par l\'apprenant sur le module, en secondes.';
$string['privacy:metadata:local_esmed_activity_log:views_count'] = 'Nombre de consultations du module par l\'apprenant.';
$string['privacy:metadata:local_esmed_activity_log:completion_state'] = 'État de complétion tel que vu par le plugin.';

$string['privacy:metadata:local_esmed_assessment_index'] = 'Tentatives d\'évaluation classifiées (quiz pédagogique, examen blanc, évaluation sommative, etc.).';
$string['privacy:metadata:local_esmed_assessment_index:userid'] = 'Utilisateur Moodle ayant réalisé la tentative.';
$string['privacy:metadata:local_esmed_assessment_index:courseid'] = 'Cours auquel l\'évaluation est rattachée.';
$string['privacy:metadata:local_esmed_assessment_index:cmid'] = 'Identifiant du module de cours de l\'évaluation.';
$string['privacy:metadata:local_esmed_assessment_index:assessment_type'] = 'Classification réglementaire de l\'évaluation.';
$string['privacy:metadata:local_esmed_assessment_index:score'] = 'Score brut obtenu.';
$string['privacy:metadata:local_esmed_assessment_index:grade_percent'] = 'Score normalisé sur 100.';
$string['privacy:metadata:local_esmed_assessment_index:attempt_date'] = 'Date de la tentative.';

$string['privacy:metadata:local_esmed_archive_index'] = 'Index des documents scellés (attestations, bordereaux) associés à un utilisateur.';
$string['privacy:metadata:local_esmed_archive_index:userid'] = 'Utilisateur Moodle concerné par le document scellé.';
$string['privacy:metadata:local_esmed_archive_index:courseid'] = 'Cours auquel le document scellé se rattache.';
$string['privacy:metadata:local_esmed_archive_index:archive_type'] = 'Type de document scellé.';
$string['privacy:metadata:local_esmed_archive_index:file_path'] = 'Chemin de stockage du fichier scellé.';
$string['privacy:metadata:local_esmed_archive_index:sha256_hash'] = 'Empreinte SHA-256 du fichier scellé.';
$string['privacy:metadata:local_esmed_archive_index:verification_token'] = 'Jeton public utilisé pour la vérification par un tiers.';
$string['privacy:metadata:local_esmed_archive_index:timestamp_sealed'] = 'Horodatage du scellement du document.';
$string['privacy:metadata:local_esmed_archive_index:retention_until'] = 'Horodatage après lequel le document devient éligible à la purge.';

$string['privacy:metadata:local_esmed_alerts'] = 'Historique des alertes de décrochage et d\'inactivité.';
$string['privacy:metadata:local_esmed_alerts:userid'] = 'Utilisateur Moodle concerné par l\'alerte.';
$string['privacy:metadata:local_esmed_alerts:courseid'] = 'Cours auquel l\'alerte se rapporte, le cas échéant.';
$string['privacy:metadata:local_esmed_alerts:alert_type'] = 'Type d\'alerte déclenché.';
$string['privacy:metadata:local_esmed_alerts:alert_data_json'] = 'Charge utile structurée attachée à l\'alerte.';
$string['privacy:metadata:local_esmed_alerts:triggered_at'] = 'Date à laquelle l\'alerte a été générée.';

$string['privacy:subcontext:sessions'] = 'Sessions de conformité';
$string['privacy:subcontext:activity'] = 'Journal d\'activité conformité';
$string['privacy:subcontext:assessments'] = 'Évaluations conformité';
$string['privacy:subcontext:archives'] = 'Archives conformité';
$string['privacy:subcontext:alerts'] = 'Alertes conformité';

$string['privacy:legalretention'] = 'Les preuves d\'assiduité sont conservées pendant la durée légale requise par l\'article L.6353-1 du Code du travail et le référentiel Qualiopi. Les demandes d\'effacement portant sur des données encore dans cette fenêtre ne sont honorées que partiellement : seules les données hors périmètre légal sont supprimées, et les identifiants directs tels que l\'adresse IP et le user-agent sont masqués.';
