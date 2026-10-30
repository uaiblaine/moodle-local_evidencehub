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
 * Brazilian Portuguese language strings.
 *
 * @package    local_evidencehub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['actionadd'] = 'Adicionar evidência';
$string['actioncomplete'] = 'Completar detalhes';
$string['actionedit'] = 'Editar';
$string['actioneditoverride'] = 'Editar exceção';
$string['actioneditpolicy'] = 'Editar política';
$string['actionlink'] = 'Vincular competências';
$string['actionoverride'] = 'Definir exceção';
$string['actionremoveoverride'] = 'Remover exceção';
$string['actionverify'] = 'Verificar ou rejeitar';
$string['alert1'] = 'Primeiro alerta (dias antes do vencimento)';
$string['alert2'] = 'Segundo alerta (dias antes do vencimento)';
$string['alerts'] = 'Alertas';
$string['alreadyrejected'] = 'Estes detalhes já estão rejeitados.';
$string['alreadyverified'] = 'Estes detalhes já estão verificados.';
$string['audit_created'] = 'Detalhes registrados';
$string['audit_details'] = 'Detalhes alterados';
$string['audit_rejected'] = 'Rejeitada';
$string['audit_reset'] = 'Voltou a pendente após uma alteração';
$string['audit_verified'] = 'Verificada';
$string['authcode'] = 'Código de autenticidade';
$string['cachedef_hubgeneration'] = 'A geração das contagens do hub de evidências';
$string['cachedef_hubkpis'] = 'As contagens do hub de evidências por filtro e por usuário';
$string['cannotverifyown'] = 'Você não pode verificar nem rejeitar a sua própria evidência.';
$string['competency'] = 'Competência';
$string['competencyidnumber'] = 'Número de identificação da competência';
$string['competencypolicies'] = 'Política de cada competência';
$string['cyclemonths'] = 'Avaliação válida por (meses)';
$string['cyclemonths_help'] = 'Por quantos meses uma avaliação proficiente comprova a competência, a partir do dia em que foi dada. 0 significa que a avaliação nunca vence.';
$string['datasource:validity'] = 'Validade das competências';
$string['decidedby'] = 'Por {$a->name} em {$a->time}';
$string['decision'] = 'Decisão';
$string['decisionreject'] = 'Rejeitar';
$string['decisionverify'] = 'Verificar';
$string['detailsmissing'] = 'Detalhes ausentes';
$string['detailsrestricted'] = 'Detalhes restritos';
$string['effectiveexpiry'] = 'Válida até';
$string['entity:details'] = 'Evidência decisiva';
$string['entity:validity'] = 'Validade da competência';
$string['errorauthcoderequired'] = 'Este tipo de evidência exige um código de autenticidade.';
$string['errorauthcodetoolong'] = 'O código de autenticidade pode ter no máximo 100 caracteres.';
$string['errorexpirybeforeissue'] = 'A data de validade não pode ser anterior à data de emissão.';
$string['errorexpirydate'] = 'A data de validade não é uma data válida.';
$string['errorexpiryrequired'] = 'Informe a data de validade ou marque "Nunca vence": este tipo não propõe uma validade.';
$string['errorhours'] = 'A carga horária não pode ser negativa.';
$string['errorhubcohort'] = 'Este coorte não pode ser usado aqui.';
$string['errorhubcontext'] = 'O hub de evidências abre apenas no site ou em uma categoria de cursos.';
$string['errorhubpairs'] = 'As linhas selecionadas não são válidas.';
$string['errorhubtemplate'] = 'Este modelo de plano de aprendizagem não é oferecido aqui.';
$string['errorissuedate'] = 'A data de emissão não é uma data válida.';
$string['errorissuertoolong'] = 'O emissor pode ter no máximo 255 caracteres.';
$string['errorlocked'] = 'Outra pessoa está alterando estes detalhes. Tente novamente em instantes.';
$string['errornoexpiry'] = 'Este tipo de evidência precisa ter data de validade.';
$string['errorpolicyalerts'] = 'O segundo alerta não pode vir antes do primeiro: dê a ele tantos dias quanto o primeiro, ou menos.';
$string['errorpolicykind'] = 'Escolha um tipo de política.';
$string['errorpolicyonexpiry'] = 'Escolha o que acontece no vencimento.';
$string['errorpolicyrange'] = 'Informe um número entre 0 e 9999.';
$string['errorrestricted'] = 'Estes detalhes são de um tipo sensível que você não pode ver, por isso não pode alterá-los nem decidir sobre eles.';
$string['errorstatuslocked'] = 'A validade deste usuário está sendo calculada por outro processo. Tente de novo em instantes.';
$string['errorunknowntype'] = 'Escolha um tipo de evidência.';
$string['errorwrongframework'] = 'Esta competência pertence a outra estrutura.';
$string['errorwronguser'] = 'Esta evidência pertence a outro usuário.';
$string['eventevidencedetailsupdated'] = 'Detalhes da evidência atualizados';
$string['eventevidencerejected'] = 'Detalhes da evidência rejeitados';
$string['eventevidenceverified'] = 'Detalhes da evidência verificados';
$string['eventhubdownloaded'] = 'Hub de evidências baixado';
$string['eventvaliditypolicyupdated'] = 'Política de validade atualizada';
$string['eventvaliditystatuschanged'] = 'Situação de validade alterada';
$string['evidence_status_critical'] = 'Hub de evidências: a evidência desta competência vence em {$a}, dentro da janela crítica.';
$string['evidence_status_due'] = 'Hub de evidências: a evidência desta competência vence em {$a}.';
$string['evidence_status_expired'] = 'Hub de evidências: a evidência desta competência venceu em {$a}.';
$string['evidence_status_unverified'] = 'Hub de evidências: há evidência desta competência, mas nenhuma conta ainda.';
$string['evidence_status_valid'] = 'Hub de evidências: uma evidência válida comprova esta competência.';
$string['evidencecount'] = 'Evidências';
$string['evidencehub:exportdossier'] = 'Exportar dossiês de prontidão';
$string['evidencehub:managepolicies'] = 'Gerenciar políticas de validade';
$string['evidencehub:managetypes'] = 'Gerenciar tipos de evidência';
$string['evidencehub:receivealerts'] = 'Receber alertas de validade de evidências';
$string['evidencehub:verify'] = 'Verificar detalhes de evidências';
$string['evidencehub:view'] = 'Ver o hub de evidências';
$string['evidencehub:viewsensitive'] = 'Ver evidências de tipos sensíveis';
$string['evidencepage'] = 'Evidências e validade';
$string['expirydate'] = 'Data de validade';
$string['expirydate_help'] = 'O último dia em que a evidência é válida. Se ficar vazia, é calculada a partir da data de emissão e da validade que o tipo propõe.';
$string['framework'] = 'Estrutura de competências';
$string['frameworkpolicy'] = 'Política da estrutura';
$string['gracedays'] = 'Dias de tolerância';
$string['gracedays_help'] = 'Dias após a data de vencimento durante os quais a evidência de uma competência técnica ainda conta.';
$string['hours'] = 'Carga horária';
$string['huball'] = 'Todos';
$string['hubanycohort'] = 'Qualquer coorte';
$string['hubapply'] = 'Aplicar';
$string['hubchoosetemplate'] = 'Escolha um modelo de plano de aprendizagem';
$string['hubchoosetemplatefirst'] = 'Escolha um modelo de plano de aprendizagem para ver seus trabalhadores.';
$string['hubdownload'] = 'Baixar';
$string['hubdownloadformat'] = 'Baixar como';
$string['hubdownloadhelp'] = 'O arquivo traz todas as linhas destes filtros, ou apenas as linhas marcadas nesta página.';
$string['hubdownloadname'] = 'hub-de-evidencias-{$a}';
$string['hubhiddenworkers'] = '{$a} trabalhadores não são mostrados: você não pode ver as evidências deles.';
$string['hublocked'] = 'Aberto a partir de {$a}: são oferecidos apenas os modelos de plano de aprendizagem desta categoria e de suas subcategorias.';
$string['hubneedsattention'] = 'Requer atenção';
$string['hubnorows'] = 'Nenhum par corresponde a estes filtros.';
$string['hubnotemplates'] = 'Não há modelo de plano de aprendizagem que você possa ler aqui.';
$string['hubnoworkers'] = '0 trabalhadores que você pode ver.';
$string['hubpage'] = 'Hub de evidências';
$string['hubperpage'] = 'Linhas por página';
$string['hubreadiness'] = 'Prontos: {$a->ready} de {$a->workers} trabalhadores ({$a->rate}%)';
$string['hubsearch'] = 'Buscar trabalhadores';
$string['hubselectall'] = 'Selecionar todas as linhas desta página';
$string['hubselectrow'] = 'Selecionar {$a}';
$string['hubshare'] = '{$a}%';
$string['hubsuspended'] = 'Incluir suspensos';
$string['hubtotal'] = '{$a} pares';
$string['issuedate'] = 'Data de emissão';
$string['issuer'] = 'Emissor';
$string['kind'] = 'Tipo de política';
$string['kind_behavioural'] = 'Comportamental';
$string['kind_help'] = 'Regulatória: só evidência conta, o vencimento é estrito e uma competência vencida impede o trabalhador. Técnica: evidência ou uma avaliação proficiente conta, com dias de tolerância. Comportamental: só uma avaliação proficiente conta, pelos meses que a política define.';
$string['kind_regulatory'] = 'Regulatória';
$string['kind_technical'] = 'Técnica';
$string['minhours'] = 'Mínimo de horas';
$string['nocompetencies'] = 'Esta estrutura ainda não tem competências.';
$string['noevidence'] = 'Ainda não há evidências de aprendizagem prévia.';
$string['noexpiry'] = 'Nunca vence';
$string['nopolicyframeworks'] = 'Não há estrutura de competências cujas políticas de validade você possa gerenciar.';
$string['note'] = 'Observação';
$string['onexpiry'] = 'No vencimento';
$string['onexpiry_help'] = 'Em ambos os casos o vencimento é registrado no histórico da competência e a proficiência nunca é revogada. Com revisão, as competências do Moodle também pedem uma revisão da competência.';
$string['onexpiry_log'] = 'Registrar';
$string['onexpiry_review'] = 'Registrar e pedir revisão';
$string['overrideremoved'] = 'A exceção foi removida: a política da estrutura volta a valer.';
$string['pluginname'] = 'Hub de evidências';
$string['policiespage'] = 'Políticas de validade';
$string['policyalerts'] = '{$a->alert1} e {$a->alert2} dias antes';
$string['privacy:metadata:core_competency'] = 'Os detalhes descrevem evidências de aprendizagem prévia que as competências do núcleo armazenam, com seus arquivos.';
$string['privacy:metadata:local_evidencehub_audit'] = 'O histórico do que aconteceu com os detalhes de cada evidência.';
$string['privacy:metadata:local_evidencehub_audit:action'] = 'O que foi feito.';
$string['privacy:metadata:local_evidencehub_audit:note'] = 'A observação registrada com a ação.';
$string['privacy:metadata:local_evidencehub_audit:relateduserid'] = 'O dono da evidência.';
$string['privacy:metadata:local_evidencehub_audit:userevidenceid'] = 'A evidência de aprendizagem prévia.';
$string['privacy:metadata:local_evidencehub_audit:userid'] = 'O usuário que agiu.';
$string['privacy:metadata:local_evidencehub_dossier'] = 'Os dossiês de prontidão emitidos sobre um usuário.';
$string['privacy:metadata:local_evidencehub_dossier:code'] = 'O código de verificação impresso no dossiê.';
$string['privacy:metadata:local_evidencehub_dossier:createdby'] = 'O usuário que emitiu o dossiê.';
$string['privacy:metadata:local_evidencehub_dossier:revoked'] = 'Se o dossiê foi revogado.';
$string['privacy:metadata:local_evidencehub_dossier:snapshot'] = 'O que o dossiê declara, como estava ao ser emitido.';
$string['privacy:metadata:local_evidencehub_dossier:templateid'] = 'O modelo de plano de aprendizagem que o dossiê cobre.';
$string['privacy:metadata:local_evidencehub_dossier:userid'] = 'O usuário que o dossiê descreve.';
$string['privacy:metadata:local_evidencehub_export'] = 'As solicitações de exportação de dossiês.';
$string['privacy:metadata:local_evidencehub_export:requestedby'] = 'O usuário que solicitou a exportação.';
$string['privacy:metadata:local_evidencehub_export:subjectcount'] = 'Quantos usuários a exportação cobre.';
$string['privacy:metadata:local_evidencehub_export:templateid'] = 'O modelo de plano de aprendizagem da exportação.';
$string['privacy:metadata:local_evidencehub_meta'] = 'Os detalhes de validade de cada evidência de aprendizagem prévia.';
$string['privacy:metadata:local_evidencehub_meta:authcode'] = 'O código de autenticidade impresso pelo emissor.';
$string['privacy:metadata:local_evidencehub_meta:expirydate'] = 'O último dia em que a evidência é válida.';
$string['privacy:metadata:local_evidencehub_meta:hours'] = 'A carga horária que a evidência declara.';
$string['privacy:metadata:local_evidencehub_meta:issuedate'] = 'A data de emissão da evidência.';
$string['privacy:metadata:local_evidencehub_meta:issuer'] = 'Quem emitiu a evidência.';
$string['privacy:metadata:local_evidencehub_meta:noexpiry'] = 'Se a evidência nunca vence.';
$string['privacy:metadata:local_evidencehub_meta:timeverified'] = 'Quando os detalhes foram verificados ou rejeitados.';
$string['privacy:metadata:local_evidencehub_meta:typeid'] = 'O tipo de evidência.';
$string['privacy:metadata:local_evidencehub_meta:userevidenceid'] = 'A evidência de aprendizagem prévia que os detalhes descrevem.';
$string['privacy:metadata:local_evidencehub_meta:userid'] = 'O dono da evidência.';
$string['privacy:metadata:local_evidencehub_meta:verifiedby'] = 'O usuário que verificou ou rejeitou os detalhes.';
$string['privacy:metadata:local_evidencehub_meta:verifynote'] = 'A observação registrada ao verificar ou rejeitar os detalhes.';
$string['privacy:metadata:local_evidencehub_meta:verifystatus'] = 'Se os detalhes estão pendentes, verificados ou rejeitados.';
$string['privacy:metadata:local_evidencehub_status'] = 'A validade de cada competência de um usuário, calculada a partir das evidências.';
$string['privacy:metadata:local_evidencehub_status:competencyid'] = 'A competência.';
$string['privacy:metadata:local_evidencehub_status:effectiveexpiry'] = 'O último dia em que a evidência decisiva é válida.';
$string['privacy:metadata:local_evidencehub_status:evidencecount'] = 'Quantas evidências foram consideradas.';
$string['privacy:metadata:local_evidencehub_status:status'] = 'A situação de validade.';
$string['privacy:metadata:local_evidencehub_status:timechanged'] = 'Quando a situação mudou pela última vez.';
$string['privacy:metadata:local_evidencehub_status:userid'] = 'O usuário.';
$string['privacy:metadata:preference:hubfilter'] = 'O último filtro usado no hub de evidências: modelo de plano de aprendizagem, coorte, situações, busca, ordenação e linhas por página.';
$string['privacy:metadata:timecreated'] = 'Quando o registro foi criado.';
$string['privacy:metadata:timemodified'] = 'Quando o registro foi alterado pela última vez.';
$string['privacy:path:details'] = 'Detalhes das evidências';
$string['privacy:path:dossiers'] = 'Dossiês de prontidão';
$string['privacy:path:requests'] = 'Solicitações de exportação de dossiês';
$string['privacy:path:statuses'] = 'Validade das competências';
$string['privacy:path:trail'] = 'Histórico das evidências';
$string['promptlink'] = 'Complete-os no hub de evidências';
$string['promptlist'] = 'Evidências sem detalhes de validade: {$a}.';
$string['promptview'] = 'Esta evidência ainda não tem detalhes de validade.';
$string['rejectneedsnote'] = 'Diga por que os detalhes foram rejeitados.';
$string['requireproficiency'] = 'Exigir avaliação proficiente';
$string['requireverified'] = 'Só evidência verificada';
$string['source'] = 'Definida por';
$string['source_competency'] = 'Esta competência';
$string['source_default'] = 'Padrão';
$string['source_framework'] = 'A estrutura';
$string['status'] = 'Situação';
$string['status_critical'] = 'A vencer, crítico';
$string['status_due'] = 'A vencer';
$string['status_expired'] = 'Expirado';
$string['status_missing'] = 'Pendente, sem evidência';
$string['status_unverified'] = 'Pendente, aguardando verificação';
$string['status_valid'] = 'Conforme';
$string['taskevaluatevalidity'] = 'Avaliar transições de validade';
$string['taskrecomputescope'] = 'Recalcular a validade após mudança de política';
$string['taskreconcilevalidity'] = 'Reconciliar situações de validade';
$string['template'] = 'Modelo de plano de aprendizagem';
$string['timechanged'] = 'Situação alterada em';
$string['type'] = 'Tipo de evidência';
$string['type_artrrt'] = 'Anotação ou registro de responsabilidade técnica (ART/RRT)';
$string['type_aso'] = 'Atestado de saúde ocupacional (ASO)';
$string['type_diploma'] = 'Diploma';
$string['type_nrcertificate'] = 'Certificado de treinamento de norma regulamentadora (NR)';
$string['type_practical'] = 'Avaliação prática';
$string['validitydetails'] = 'Detalhes de validade';
$string['validityrestricted'] = 'Restrito';
$string['validuntil'] = 'até {$a}';
$string['verification'] = 'Verificação';
$string['verify_pending'] = 'Aguardando verificação';
$string['verify_rejected'] = 'Rejeitada';
$string['verify_verified'] = 'Verificada';
$string['worker'] = 'Trabalhador';
