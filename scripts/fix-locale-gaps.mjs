import fs from 'fs';
import path from 'path';

const root = path.resolve(import.meta.dirname, '..');
const localesDir = path.join(root, 'resources/js/locales');

const targets = [
  { lang: 'fr', file: 'assets.json' },
  { lang: 'de', file: 'assets.json' },
  { lang: 'ar', file: 'assets.json' },
  { lang: 'fr', file: 'service_desk.json' },
  { lang: 'de', file: 'service_desk.json' },
  { lang: 'ar', file: 'service_desk.json' },
  { lang: 'es', file: 'pages.json' },
  { lang: 'fr', file: 'pages.json' },
  { lang: 'de', file: 'pages.json' },
  { lang: 'ar', file: 'pages.json' },
];

const translations = {
  fr: {
    'assets.completed': 'Terminé',
    'assets.create_type': 'Créer le type',
    'assets.delete': 'Supprimer',
    'assets.delete_asset_type': 'Supprimer le type d\'actif ?',
    'assets.device_fallback_name': 'Appareil {ip}',
    'assets.device_status_imported': 'Importé',
    'assets.device_status_matched': 'Correspondant',
    'assets.device_status_new': 'Nouveau',
    'assets.device_status_skipped': 'Ignoré',
    'assets.devices_found_count': '{count} appareils trouvés',
    'assets.edit_asset_type': 'Modifier le type d\'actif',
    'assets.failed': 'Échoué',
    'assets.import_selected': 'Importer la sélection ({count})',
    'assets.ip_address_placeholder': '10.0.0.12',
    'assets.issue_with_asset_tag': 'Problème avec {tag}',
    'assets.new_asset_type': 'Nouveau type d\'actif',
    'assets.no_asset_types_yet': 'Aucun type d\'actif pour le moment.',
    'assets.no_assets_found': 'Aucun actif trouvé.',
    'assets.no_discovery_scans_yet': 'Aucune analyse de découverte pour le moment.',
    'assets.ok': 'OK',
    'assets.page_title_scan': 'Analyse {subnet}',
    'assets.pending': 'En attente',
    'assets.private_randomized_mac': 'MAC privée / aléatoire',
    'assets.purchase_cost_placeholder': '1299.00',
    'assets.remove_type_permanently': 'Supprimer « {name} » définitivement ?',
    'assets.report_issue': 'Signaler un problème',
    'assets.required_columns': 'Colonnes requises :',
    'assets.running': 'En cours',
    'assets.started_by_label': ' · lancé par {name}',
    'assets.type_has_assets_cannot_delete': '« {name} » a {count} actifs et ne peut pas être supprimé tant qu\'ils ne sont pas réaffectés.',
    'assets.unassigned_only': 'Non assignés uniquement',
    'assets.warranty_expiring_in_30_days': 'Garantie expirant dans 30 jours',
    'assets.add_printer_router_and_other_categories': ' pour ajouter Imprimante, Routeur et d\'autres catégories.',
    'assets.assignment_assigned': 'Assigné',
    'assets.assignment_organization_changed': 'Organisation modifiée',
    'assets.assignment_unassigned': 'Non assigné',
    'assets.back_to_assets': '← Retour aux actifs',
    'assets.back_to_discovery': '← Retour à la découverte',
    'assets.csv_optional_columns': 'Facultatif : étiquette d\'actif, statut, numéro de série, e-mail du contact, organisation, emplacement, IP, MAC, nom d\'hôte, fabricant, modèle, fournisseur, coût d\'achat, date d\'achat, fin de garantie, notes.',
    'service_desk.back_to_major_incidents': '← Incidents majeurs',
    'service_desk.completed_by': 'Terminé par {name}',
    'service_desk.current_plan_message': 'Vous êtes sur le forfait {plan}. Service Desk ajoute des files ITIL, des workflows d\'approbation, la gestion des changements et des problèmes, et des salles de crise au-dessus de vos tickets et catalogue existants.',
    'service_desk.declared_by': 'par {name}',
    'service_desk.declared_by_suffix': ' · déclaré par {name}',
    'service_desk.feature_approval_inbox': 'Boîte d\'approbation catalogue et changements avec actions e-mail et in-app',
    'service_desk.feature_change_calendar': 'Calendrier des changements, liaison des problèmes et salles de crise avec revue post-incident',
    'service_desk.feature_separate_queues': 'Files séparées pour incidents, demandes de service, changements et problèmes',
    'service_desk.full_ticket_view': 'Vue ticket complète',
    'service_desk.major_incident_status': 'Incident majeur · {status}',
    'service_desk.manage_service_catalog': 'Gérer le catalogue de services',
    'service_desk.no_approval_requests': 'Aucune demande d\'approbation ne correspond à cette vue.',
    'service_desk.no_items_yet': 'Aucun {type} pour le moment.',
    'service_desk.no_major_incidents_or_reviews': 'Aucun incident majeur actif ni revue en attente.',
    'service_desk.no_matches_filters': 'Aucun {type} ne correspond à vos filtres.',
    'service_desk.no_scheduled_changes': 'Aucun changement planifié sur cette période.',
    'service_desk.open_badge': '{count} ouverts',
    'service_desk.open_war_rooms': 'Ouvrir les salles de crise',
    'service_desk.page_title_war_room': 'Salle de crise · {number}',
    'service_desk.pending_review_count': ' · {count} en attente de revue',
    'service_desk.queue_description': '{total} {type}',
    'service_desk.queue_title': 'File {singular}',
    'service_desk.recent_items': '{type} récents',
    'service_desk.review_my_approvals': 'Examiner mes approbations',
    'service_desk.summary_required': 'Résumé *',
    'service_desk.upgrade_to_enterprise': 'Passer à Enterprise',
    'service_desk.view_queue': 'Voir la file',
    'service_desk.view_ticket': 'Voir le ticket',
    'service_desk.waiting_on_you': '{count} vous attendent',
  },
  de: {
    'assets.completed': 'Abgeschlossen',
    'assets.create_type': 'Typ erstellen',
    'assets.delete': 'Löschen',
    'assets.delete_asset_type': 'Asset-Typ löschen?',
    'assets.device_fallback_name': 'Gerät {ip}',
    'assets.device_status_imported': 'Importiert',
    'assets.device_status_matched': 'Zugeordnet',
    'assets.device_status_new': 'Neu',
    'assets.device_status_skipped': 'Übersprungen',
    'assets.devices_found_count': '{count} Geräte gefunden',
    'assets.edit_asset_type': 'Asset-Typ bearbeiten',
    'assets.failed': 'Fehlgeschlagen',
    'assets.import_selected': 'Auswahl importieren ({count})',
    'assets.ip_address_placeholder': '10.0.0.12',
    'assets.issue_with_asset_tag': 'Problem mit {tag}',
    'assets.new_asset_type': 'Neuer Asset-Typ',
    'assets.no_asset_types_yet': 'Noch keine Asset-Typen.',
    'assets.no_assets_found': 'Keine Assets gefunden.',
    'assets.no_discovery_scans_yet': 'Noch keine Discovery-Scans.',
    'assets.ok': 'OK',
    'assets.page_title_scan': 'Scan {subnet}',
    'assets.pending': 'Ausstehend',
    'assets.private_randomized_mac': 'Private / zufällige MAC',
    'assets.purchase_cost_placeholder': '1299.00',
    'assets.remove_type_permanently': '„{name}" dauerhaft entfernen?',
    'assets.report_issue': 'Problem melden',
    'assets.required_columns': 'Erforderliche Spalten:',
    'assets.running': 'Läuft',
    'assets.started_by_label': ' · gestartet von {name}',
    'assets.type_has_assets_cannot_delete': '„{name}" hat {count} Assets und kann nicht gelöscht werden, bis sie neu zugewiesen sind.',
    'assets.unassigned_only': 'Nur nicht zugewiesene',
    'assets.warranty_expiring_in_30_days': 'Garantie läuft in 30 Tagen ab',
    'assets.add_printer_router_and_other_categories': ', um Drucker, Router und andere Kategorien hinzuzufügen.',
    'assets.assignment_assigned': 'Zugewiesen',
    'assets.assignment_organization_changed': 'Organisation geändert',
    'assets.assignment_unassigned': 'Nicht zugewiesen',
    'assets.back_to_assets': '← Zurück zu Assets',
    'assets.back_to_discovery': '← Zurück zur Erkennung',
    'assets.csv_optional_columns': 'Optional: Asset-Tag, Status, Seriennummer, Kontakt-E-Mail, Organisation, Standort, IP, MAC, Hostname, Hersteller, Modell, Anbieter, Anschaffungskosten, Kaufdatum, Garantieablauf, Notizen.',
    'service_desk.back_to_major_incidents': '← Schwerwiegende Vorfälle',
    'service_desk.completed_by': 'Abgeschlossen von {name}',
    'service_desk.current_plan_message': 'Sie nutzen den {plan}-Plan. Service Desk ergänzt ITIL-Typ-Warteschlangen, Genehmigungsworkflows, Change- und Problem-Management sowie War Rooms zusätzlich zu Ihren bestehenden Tickets und dem Servicekatalog.',
    'service_desk.declared_by': 'von {name}',
    'service_desk.declared_by_suffix': ' · gemeldet von {name}',
    'service_desk.feature_approval_inbox': 'Katalog- und Change-Genehmigungs-Posteingang mit E-Mail- und In-App-Aktionen',
    'service_desk.feature_change_calendar': 'Change-Kalender, Problem-Verknüpfung und War Rooms mit Post-Incident-Review',
    'service_desk.feature_separate_queues': 'Getrennte Warteschlangen für Vorfälle, Serviceanfragen, Changes und Probleme',
    'service_desk.full_ticket_view': 'Vollständige Ticketansicht',
    'service_desk.major_incident_status': 'Schwerwiegender Vorfall · {status}',
    'service_desk.manage_service_catalog': 'Servicekatalog verwalten',
    'service_desk.no_approval_requests': 'Keine Genehmigungsanfragen entsprechen dieser Ansicht.',
    'service_desk.no_items_yet': 'Noch keine {type}.',
    'service_desk.no_major_incidents_or_reviews': 'Keine aktiven schwerwiegenden Vorfälle oder ausstehenden Reviews.',
    'service_desk.no_matches_filters': 'Keine {type} entsprechen Ihren Filtern.',
    'service_desk.no_scheduled_changes': 'Keine geplanten Changes in diesem Zeitraum.',
    'service_desk.open_badge': '{count} offen',
    'service_desk.open_war_rooms': 'War Rooms öffnen',
    'service_desk.page_title_war_room': 'War Room · {number}',
    'service_desk.pending_review_count': ' · {count} Review ausstehend',
    'service_desk.queue_description': '{total} {type}',
    'service_desk.queue_title': '{singular}-Warteschlange',
    'service_desk.recent_items': 'Aktuelle {type}',
    'service_desk.review_my_approvals': 'Meine Genehmigungen prüfen',
    'service_desk.summary_required': 'Zusammenfassung *',
    'service_desk.upgrade_to_enterprise': 'Auf Enterprise upgraden',
    'service_desk.view_queue': 'Warteschlange anzeigen',
    'service_desk.view_ticket': 'Ticket anzeigen',
    'service_desk.waiting_on_you': '{count} warten auf Sie',
  },
  ar: {
    'assets.completed': 'مكتمل',
    'assets.create_type': 'إنشاء النوع',
    'assets.delete': 'حذف',
    'assets.delete_asset_type': 'حذف نوع الأصل؟',
    'assets.device_fallback_name': 'جهاز {ip}',
    'assets.device_status_imported': 'مُستورد',
    'assets.device_status_matched': 'مطابق',
    'assets.device_status_new': 'جديد',
    'assets.device_status_skipped': 'مُتخطى',
    'assets.devices_found_count': '{count} أجهزة موجودة',
    'assets.edit_asset_type': 'تعديل نوع الأصل',
    'assets.failed': 'فشل',
    'assets.import_selected': 'استيراد المحدد ({count})',
    'assets.ip_address_placeholder': '10.0.0.12',
    'assets.issue_with_asset_tag': 'مشكلة مع {tag}',
    'assets.new_asset_type': 'نوع أصل جديد',
    'assets.no_asset_types_yet': 'لا توجد أنواع أصول بعد.',
    'assets.no_assets_found': 'لم يُعثر على أصول.',
    'assets.no_discovery_scans_yet': 'لا توجد عمليات مسح اكتشاف بعد.',
    'assets.ok': 'OK',
    'assets.page_title_scan': 'مسح {subnet}',
    'assets.pending': 'معلق',
    'assets.private_randomized_mac': 'MAC خاص / عشوائي',
    'assets.purchase_cost_placeholder': '1299.00',
    'assets.remove_type_permanently': 'إزالة «{name}» نهائياً؟',
    'assets.report_issue': 'الإبلاغ عن مشكلة',
    'assets.required_columns': 'الأعمدة المطلوبة:',
    'assets.running': 'قيد التشغيل',
    'assets.started_by_label': ' · بدأه {name}',
    'assets.type_has_assets_cannot_delete': '«{name}» لديه {count} أصول ولا يمكن حذفه حتى إعادة تعيينها.',
    'assets.unassigned_only': 'غير المُعيَّن فقط',
    'assets.warranty_expiring_in_30_days': 'الضمان ينتهي خلال 30 يوماً',
    'assets.add_printer_router_and_other_categories': ' لإضافة طابعة وموجه وفئات أخرى.',
    'assets.assignment_assigned': 'مُعيَّن',
    'assets.assignment_organization_changed': 'تغيّرت المؤسسة',
    'assets.assignment_unassigned': 'غير مُعيَّن',
    'assets.back_to_assets': '← العودة للأصول',
    'assets.back_to_discovery': '← العودة للاكتشاف',
    'assets.csv_optional_columns': 'اختياري: وسم الأصل، الحالة، الرقم التسلسلي، بريد جهة الاتصال، المؤسسة، الموقع، IP، MAC، اسم المضيف، الشركة المصنعة، الطراز، المورد، تكلفة الشراء، تاريخ الشراء، انتهاء الضمان، ملاحظات.',
    'service_desk.back_to_major_incidents': '← الحوادث الكبرى',
    'service_desk.completed_by': 'أكمله {name}',
    'service_desk.current_plan_message': 'أنت على خطة {plan}. يضيف Service Desk طوابير ITSM ومسارات الموافقة وإدارة التغيير والمشكلات وغرف الأزمة فوق تذاكرك وكتالوج الخدمات الحالي.',
    'service_desk.declared_by': 'بواسطة {name}',
    'service_desk.declared_by_suffix': ' · أعلنه {name}',
    'service_desk.feature_approval_inbox': 'صندوق موافقات الكتالوج والتغيير مع إجراءات البريد والتطبيق',
    'service_desk.feature_change_calendar': 'تقويم التغيير وربط المشكلات وغرف الأزمة مع مراجعة ما بعد الحادث',
    'service_desk.feature_separate_queues': 'طوابير منفصلة للحوادث وطلبات الخدمة والتغييرات والمشكلات',
    'service_desk.full_ticket_view': 'عرض التذكرة الكامل',
    'service_desk.major_incident_status': 'حادث كبير · {status}',
    'service_desk.manage_service_catalog': 'إدارة كتالوج الخدمات',
    'service_desk.no_approval_requests': 'لا توجد طلبات موافقة تطابق هذا العرض.',
    'service_desk.no_items_yet': 'لا يوجد {type} بعد.',
    'service_desk.no_major_incidents_or_reviews': 'لا توجد حوادث كبرى نشطة أو مراجعات معلقة.',
    'service_desk.no_matches_filters': 'لا يوجد {type} يطابق فلاترك.',
    'service_desk.no_scheduled_changes': 'لا توجد تغييرات مجدولة في هذه الفترة.',
    'service_desk.open_badge': '{count} مفتوح',
    'service_desk.open_war_rooms': 'فتح غرف الأزمة',
    'service_desk.page_title_war_room': 'غرفة الأزمة · {number}',
    'service_desk.pending_review_count': ' · {count} مراجعة معلقة',
    'service_desk.queue_description': '{total} {type}',
    'service_desk.queue_title': 'طابور {singular}',
    'service_desk.recent_items': '{type} الأخيرة',
    'service_desk.review_my_approvals': 'مراجعة موافقاتي',
    'service_desk.summary_required': 'الملخص *',
    'service_desk.upgrade_to_enterprise': 'الترقية إلى Enterprise',
    'service_desk.view_queue': 'عرض الطابور',
    'service_desk.view_ticket': 'عرض التذكرة',
    'service_desk.waiting_on_you': '{count} في انتظارك',
  },
};

const pagesTranslations = JSON.parse(
  fs.readFileSync(path.join(import.meta.dirname, 'pages-translations.json'), 'utf8')
);

function flatten(obj, prefix = '') {
  const out = {};
  for (const k of Object.keys(obj)) {
    const full = prefix ? `${prefix}.${k}` : k;
    if (typeof obj[k] === 'object' && obj[k] !== null && !Array.isArray(obj[k])) {
      Object.assign(out, flatten(obj[k], full));
    } else {
      out[full] = obj[k];
    }
  }
  return out;
}

function unflatten(flat) {
  const out = {};
  for (const [key, value] of Object.entries(flat)) {
    const parts = key.split('.');
    let cur = out;
    for (let i = 0; i < parts.length - 1; i++) {
      cur[parts[i]] ??= {};
      cur = cur[parts[i]];
    }
    cur[parts[parts.length - 1]] = value;
  }
  return out;
}

function mergeLocale(lang, file) {
  const enPath = path.join(localesDir, 'en', file);
  const trPath = path.join(localesDir, lang, file);
  const en = JSON.parse(fs.readFileSync(enPath, 'utf8'));
  const existing = fs.existsSync(trPath) ? JSON.parse(fs.readFileSync(trPath, 'utf8')) : {};
  const enFlat = flatten(en);
  const existingFlat = flatten(existing);
  const langMap = { ...translations[lang], ...pagesTranslations[lang] };
  const merged = {};
  for (const [key, enVal] of Object.entries(enFlat)) {
    merged[key] = existingFlat[key] ?? langMap[key] ?? enVal;
  }
  fs.writeFileSync(trPath, JSON.stringify(unflatten(merged), null, 4) + '\n');
}

for (const { lang, file } of targets) {
  mergeLocale(lang, file);
}

console.log('Merged locale files.');
