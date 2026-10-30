<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * English language strings.
 *
 * @package    local_evidencehub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['actionadd'] = 'Add evidence';
$string['actioncomplete'] = 'Complete details';
$string['actionedit'] = 'Edit';
$string['actionlink'] = 'Link competencies';
$string['actionverify'] = 'Verify or reject';
$string['alreadyrejected'] = 'These details are already rejected.';
$string['alreadyverified'] = 'These details are already verified.';
$string['audit_created'] = 'Details entered';
$string['audit_details'] = 'Details changed';
$string['audit_rejected'] = 'Rejected';
$string['audit_reset'] = 'Sent back to pending after a change';
$string['audit_verified'] = 'Verified';
$string['authcode'] = 'Authenticity code';
$string['cannotverifyown'] = 'You cannot verify or reject your own evidence.';
$string['decidedby'] = 'By {$a->name} on {$a->time}';
$string['decision'] = 'Decision';
$string['decisionreject'] = 'Reject';
$string['decisionverify'] = 'Verify';
$string['detailsmissing'] = 'Details missing';
$string['detailsrestricted'] = 'Restricted details';
$string['errorauthcoderequired'] = 'This type of evidence needs an authenticity code.';
$string['errorauthcodetoolong'] = 'The authenticity code can be at most 100 characters long.';
$string['errorexpirybeforeissue'] = 'The expiry date cannot be before the issue date.';
$string['errorexpirydate'] = 'The expiry date is not a valid date.';
$string['errorexpiryrequired'] = 'Give an expiry date or tick "Never expires": this type proposes no validity.';
$string['errorhours'] = 'The hours cannot be negative.';
$string['errorissuedate'] = 'The issue date is not a valid date.';
$string['errorissuertoolong'] = 'The issuer can be at most 255 characters long.';
$string['errorlocked'] = 'Someone else is changing these details. Try again in a moment.';
$string['errornoexpiry'] = 'This type of evidence must have an expiry date.';
$string['errorrestricted'] = 'These details are of a sensitive type you cannot see, so you cannot change or decide on them.';
$string['errorunknowntype'] = 'Choose a type of evidence.';
$string['errorwronguser'] = 'This evidence belongs to another user.';
$string['eventevidencedetailsupdated'] = 'Evidence details updated';
$string['eventevidencerejected'] = 'Evidence details rejected';
$string['eventevidenceverified'] = 'Evidence details verified';
$string['evidencehub:exportdossier'] = 'Export readiness dossiers';
$string['evidencehub:managepolicies'] = 'Manage validity policies';
$string['evidencehub:managetypes'] = 'Manage evidence types';
$string['evidencehub:receivealerts'] = 'Receive evidence validity alerts';
$string['evidencehub:verify'] = 'Verify evidence details';
$string['evidencehub:view'] = 'View the evidence hub';
$string['evidencehub:viewsensitive'] = 'View evidence of sensitive types';
$string['evidencepage'] = 'Evidence and validity';
$string['expirydate'] = 'Expiry date';
$string['expirydate_help'] = 'The last day the evidence is valid. Left empty, it is worked out from the issue date and the validity the type proposes.';
$string['hours'] = 'Hours';
$string['issuedate'] = 'Issue date';
$string['issuer'] = 'Issuer';
$string['noevidence'] = 'No evidence of prior learning yet.';
$string['noexpiry'] = 'Never expires';
$string['note'] = 'Note';
$string['pluginname'] = 'Evidence hub';
$string['privacy:metadata:core_competency'] = 'The details describe evidence of prior learning that core competencies stores, with its files.';
$string['privacy:metadata:local_evidencehub_audit'] = 'The trail of what happened to the details of each evidence.';
$string['privacy:metadata:local_evidencehub_audit:action'] = 'What was done.';
$string['privacy:metadata:local_evidencehub_audit:note'] = 'The note given with the action.';
$string['privacy:metadata:local_evidencehub_audit:relateduserid'] = 'The owner of the evidence.';
$string['privacy:metadata:local_evidencehub_audit:userevidenceid'] = 'The evidence of prior learning.';
$string['privacy:metadata:local_evidencehub_audit:userid'] = 'The user who acted.';
$string['privacy:metadata:local_evidencehub_dossier'] = 'The readiness dossiers issued about a user.';
$string['privacy:metadata:local_evidencehub_dossier:code'] = 'The verification code printed on the dossier.';
$string['privacy:metadata:local_evidencehub_dossier:createdby'] = 'The user who issued the dossier.';
$string['privacy:metadata:local_evidencehub_dossier:revoked'] = 'Whether the dossier was revoked.';
$string['privacy:metadata:local_evidencehub_dossier:snapshot'] = 'What the dossier states, as it was when issued.';
$string['privacy:metadata:local_evidencehub_dossier:templateid'] = 'The learning plan template the dossier covers.';
$string['privacy:metadata:local_evidencehub_dossier:userid'] = 'The user the dossier describes.';
$string['privacy:metadata:local_evidencehub_export'] = 'The dossier export requests.';
$string['privacy:metadata:local_evidencehub_export:requestedby'] = 'The user who requested the export.';
$string['privacy:metadata:local_evidencehub_export:subjectcount'] = 'How many users the export covers.';
$string['privacy:metadata:local_evidencehub_export:templateid'] = 'The learning plan template of the export.';
$string['privacy:metadata:local_evidencehub_meta'] = 'The validity details of each evidence of prior learning.';
$string['privacy:metadata:local_evidencehub_meta:authcode'] = 'The authenticity code the issuer printed.';
$string['privacy:metadata:local_evidencehub_meta:expirydate'] = 'The last day the evidence is valid.';
$string['privacy:metadata:local_evidencehub_meta:hours'] = 'The hours of training the evidence states.';
$string['privacy:metadata:local_evidencehub_meta:issuedate'] = 'The date the evidence was issued.';
$string['privacy:metadata:local_evidencehub_meta:issuer'] = 'Who issued the evidence.';
$string['privacy:metadata:local_evidencehub_meta:noexpiry'] = 'Whether the evidence never expires.';
$string['privacy:metadata:local_evidencehub_meta:timeverified'] = 'When the details were verified or rejected.';
$string['privacy:metadata:local_evidencehub_meta:typeid'] = 'The type of evidence.';
$string['privacy:metadata:local_evidencehub_meta:userevidenceid'] = 'The evidence of prior learning the details describe.';
$string['privacy:metadata:local_evidencehub_meta:userid'] = 'The owner of the evidence.';
$string['privacy:metadata:local_evidencehub_meta:verifiedby'] = 'The user who verified or rejected the details.';
$string['privacy:metadata:local_evidencehub_meta:verifynote'] = 'The note given when the details were verified or rejected.';
$string['privacy:metadata:local_evidencehub_meta:verifystatus'] = 'Whether the details are pending, verified or rejected.';
$string['privacy:metadata:local_evidencehub_status'] = 'The validity of each competency of a user, worked out from the evidence.';
$string['privacy:metadata:local_evidencehub_status:competencyid'] = 'The competency.';
$string['privacy:metadata:local_evidencehub_status:effectiveexpiry'] = 'The last day the deciding evidence is valid.';
$string['privacy:metadata:local_evidencehub_status:evidencecount'] = 'How many evidence records were considered.';
$string['privacy:metadata:local_evidencehub_status:status'] = 'The validity status.';
$string['privacy:metadata:local_evidencehub_status:timechanged'] = 'When the status last changed.';
$string['privacy:metadata:local_evidencehub_status:userid'] = 'The user.';
$string['privacy:metadata:timecreated'] = 'When the record was created.';
$string['privacy:metadata:timemodified'] = 'When the record was last changed.';
$string['privacy:path:details'] = 'Evidence details';
$string['privacy:path:dossiers'] = 'Readiness dossiers';
$string['privacy:path:requests'] = 'Dossier export requests';
$string['privacy:path:statuses'] = 'Competency validity';
$string['privacy:path:trail'] = 'Evidence audit trail';
$string['promptlink'] = 'Complete them in the evidence hub';
$string['promptlist'] = 'Evidence records without validity details: {$a}.';
$string['promptview'] = 'This evidence has no validity details yet.';
$string['rejectneedsnote'] = 'Say why the details are rejected.';
$string['status_critical'] = 'Due, critical';
$string['status_due'] = 'Due soon';
$string['status_expired'] = 'Expired';
$string['status_missing'] = 'Pending, no evidence';
$string['status_unverified'] = 'Pending, awaiting verification';
$string['status_valid'] = 'Compliant';
$string['type'] = 'Type of evidence';
$string['type_artrrt'] = 'Technical responsibility record (ART/RRT)';
$string['type_aso'] = 'Occupational health certificate (ASO)';
$string['type_diploma'] = 'Diploma';
$string['type_nrcertificate'] = 'Regulatory training certificate (NR)';
$string['type_practical'] = 'Practical assessment';
$string['validitydetails'] = 'Validity details';
$string['verify_pending'] = 'Awaiting verification';
$string['verify_rejected'] = 'Rejected';
$string['verify_verified'] = 'Verified';
