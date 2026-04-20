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

// Paramètres (les écrans d\'administration sont livrés à l\'itération 2).
$string['settings_general'] = 'Général';
$string['setting_heartbeat_interval'] = 'Intervalle de heartbeat (secondes)';
$string['setting_heartbeat_interval_desc'] = 'Fréquence à laquelle le navigateur envoie un signal de présence tant que l\'utilisateur est sur une page Moodle.';
$string['setting_session_timeout_minutes'] = 'Délai d\'inactivité avant clôture (minutes)';
$string['setting_session_timeout_minutes_desc'] = 'Les sessions ouvertes sans heartbeat depuis plus longtemps que cette durée sont clôturées automatiquement en mode « timeout ».';
$string['setting_activity_delta_cap_minutes'] = 'Plafond du delta inter-vues (minutes)';
$string['setting_activity_delta_cap_minutes_desc'] = 'Durée maximale comptabilisée entre deux consultations consécutives d\'un même module.';
$string['setting_retention_years'] = 'Durée de conservation (années)';
$string['setting_retention_years_desc'] = 'Durée pendant laquelle les documents scellés sont conservés avant de devenir éligibles à la purge.';
$string['setting_archive_storage_adapter'] = 'Adaptateur de stockage des archives';
$string['setting_archive_storage_adapter_desc'] = 'Destination des documents scellés (système de fichiers local ou stockage objet S3).';

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
$string['privacy:metadata:local_esmed_activity_log'] = 'Temps passé et nombre de consultations agrégés par module de cours.';
$string['privacy:metadata:local_esmed_assessment_index'] = 'Tentatives d\'évaluation classifiées (quiz pédagogique, examen blanc, évaluation sommative, etc.).';
$string['privacy:metadata:local_esmed_archive_index'] = 'Index des documents scellés (attestations, bordereaux) associés à un utilisateur.';
$string['privacy:metadata:local_esmed_alerts'] = 'Historique des alertes de décrochage et d\'inactivité.';
$string['privacy:legalretention'] = 'Les preuves d\'assiduité sont conservées pendant la durée légale requise par l\'article L.6353-1 du Code du travail et le référentiel Qualiopi. Les demandes d\'effacement portant sur des données encore dans cette fenêtre ne sont honorées que partiellement : seules les données hors périmètre légal sont supprimées.';
