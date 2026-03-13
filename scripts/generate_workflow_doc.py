#!/usr/bin/env python3
"""Génère le document Word COMPACT : Workflow et Expérience Utilisateur par Rôle — CHAPCHAP"""

from docx import Document
from docx.shared import Pt, RGBColor, Cm
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.enum.table import WD_TABLE_ALIGNMENT
from docx.oxml.ns import qn

doc = Document()

# ── Styles globaux COMPACTS ──
style = doc.styles['Normal']
style.font.name = 'Calibri'
style.font.size = Pt(9)
style.paragraph_format.space_after = Pt(2)
style.paragraph_format.space_before = Pt(0)

# Réduire les marges du document
for section in doc.sections:
    section.top_margin = Cm(1.5)
    section.bottom_margin = Cm(1.5)
    section.left_margin = Cm(1.8)
    section.right_margin = Cm(1.8)

# Réduire la taille des titres
for level in range(1, 4):
    hs = doc.styles[f'Heading {level}']
    hs.paragraph_format.space_before = Pt(8 if level == 1 else 6)
    hs.paragraph_format.space_after = Pt(3)
    hs.font.size = Pt(14 if level == 1 else 11)

for ls in ['List Bullet', 'List Number']:
    try:
        s = doc.styles[ls]
        s.font.size = Pt(9)
        s.paragraph_format.space_after = Pt(1)
        s.paragraph_format.space_before = Pt(0)
    except KeyError:
        pass

ORANGE = RGBColor(0xE8, 0x6C, 0x00)
DARK = RGBColor(0x33, 0x33, 0x33)
RED = RGBColor(0xCC, 0x00, 0x00)

# ── Fonctions utilitaires ──
def heading(text, level=1, color=ORANGE):
    h = doc.add_heading(text, level=level)
    for run in h.runs:
        run.font.color.rgb = color
    return h

def bullet(text, bold_prefix=None):
    p = doc.add_paragraph(style='List Bullet')
    if bold_prefix:
        p.add_run(bold_prefix).bold = True
        p.add_run(text)
    else:
        p.add_run(text)
    return p

def numbered(text, bold_prefix=None):
    p = doc.add_paragraph(style='List Number')
    if bold_prefix:
        p.add_run(bold_prefix).bold = True
        p.add_run(text)
    else:
        p.add_run(text)
    return p

def para(text, bold=False, size=9):
    p = doc.add_paragraph()
    r = p.add_run(text)
    r.font.size = Pt(size)
    r.bold = bold
    return p

def add_row(table, cells, bold=False):
    row = table.add_row()
    for i, text in enumerate(cells):
        cell = row.cells[i]
        cell.text = ''
        r = cell.paragraphs[0].add_run(str(text))
        r.font.size = Pt(8)
        r.bold = bold
    return row

def color_header(table, color='E86C00'):
    for cell in table.rows[0].cells:
        tc = cell._element.get_or_add_tcPr()
        tc.append(tc.makeelement(qn('w:shd'), {qn('w:fill'): color, qn('w:val'): 'clear'}))
        for p in cell.paragraphs:
            for r in p.runs:
                r.font.color.rgb = RGBColor(0xFF, 0xFF, 0xFF)
                r.bold = True
                r.font.size = Pt(8)

def make_table(headers, rows_data):
    t = doc.add_table(rows=1, cols=len(headers))
    t.style = 'Light Grid Accent 1'
    t.alignment = WD_TABLE_ALIGNMENT.CENTER
    for i, h in enumerate(headers):
        t.rows[0].cells[i].text = h
    color_header(t)
    for row in rows_data:
        add_row(t, row)
    return t


# ══════════════════════════════════════════════════════════
# PAGE DE GARDE (compacte)
# ══════════════════════════════════════════════════════════
doc.add_paragraph()
p = doc.add_paragraph()
p.alignment = WD_ALIGN_PARAGRAPH.CENTER
r = p.add_run('CHAPCHAP')
r.font.size = Pt(30)
r.bold = True
r.font.color.rgb = ORANGE

p = doc.add_paragraph()
p.alignment = WD_ALIGN_PARAGRAPH.CENTER
r = p.add_run('Application de Gestion Intégrée — Boucherie & Transformation de Volailles')
r.font.size = Pt(12)
r.font.color.rgb = RGBColor(0x55, 0x55, 0x55)

p = doc.add_paragraph()
p.alignment = WD_ALIGN_PARAGRAPH.CENTER
r = p.add_run('Workflow & Expérience Utilisateur par Rôle')
r.font.size = Pt(16)
r.bold = True

p = doc.add_paragraph()
p.alignment = WD_ALIGN_PARAGRAPH.CENTER
r = p.add_run('Document de référence — Février 2026 — Abidjan, Côte d\'Ivoire')
r.font.size = Pt(9)
r.font.color.rgb = RGBColor(0x88, 0x88, 0x88)

doc.add_page_break()

# ══════════════════════════════════════════════════════════
# 1. VUE D'ENSEMBLE DES RÔLES
# ══════════════════════════════════════════════════════════
heading('1. Vue d\'ensemble des rôles')
para('CHAPCHAP définit 6 profils utilisateurs (+1 super administrateur technique). Chaque rôle a un périmètre d\'action précis et un dashboard adapté.')

make_table(
    ['Profil', 'Slug', 'Périmètre', 'Résumé des droits'],
    [
        ('Super Admin', 'super_admin', 'Technique', 'Gestion utilisateurs, rôles, permissions, config système'),
        ('Gérant', 'gerant', 'Global', 'Supervision, validation, KPI, exports'),
        ('Resp. Opérations', 'resp_operations', 'Global', 'Pilotage quotidien, paramétrage, contrôle'),
        ('Gest. Stock & Hygiène', 'gestionnaire_stock', 'Stock/Lots', 'Mouvements stock, inventaires, lots, caisse'),
        ('Agent Production', 'agent_production', 'Production', 'Saisie production, consultation stock'),
        ('Commercial / PdV', 'commercial', 'Ventes', 'Ventes, clients, encaissements, impayés'),
    ]
)

# ══════════════════════════════════════════════════════════
# 2. GÉRANT
# ══════════════════════════════════════════════════════════
heading('2. Gérant — Supervision globale')
para('Vision : Supervision globale et prise de décision stratégique', bold=True)

heading('Workflow quotidien', level=2, color=DARK)
numbered('Se connecte et visualise le Dashboard Gérant avec tous les KPI')
numbered('Consulte les lots, productions, ventes, stocks en temps réel')
numbered('Valide les opérations sensibles (productions, inventaires, clôtures de caisse)')
numbered('Exporte les rapports PDF/Excel pour analyse')
numbered('Gère les factures fournisseurs (suivi des règlements)')

heading('Dashboard — KPI affichés', level=2, color=DARK)
for prefix, suffix in [
    ('CA jour/semaine/mois', ' avec comparaison N-1'), ('Marge brute', ' globale et par produit'),
    ('Top 5 clients', ' par CA'), ('Top 5 produits', ' par volume'),
    ('Impayés en cours', ' avec ancienneté'), ('Trésorerie', ' encaissements vs décaissements'),
    ('Stock valorisé', ' en FCFA'),
]:
    bullet(suffix, bold_prefix=prefix)

para('Droits : CRUD + suppression sur toutes données métier. Consultation mouvements stock. '
     'Gestion utilisateurs/rôles réservée au super_admin.')

# ══════════════════════════════════════════════════════════
# 3. RESP. OPÉRATIONS
# ══════════════════════════════════════════════════════════
heading('3. Resp. Opérations & Admin — Pilotage quotidien')
para('Vision : Pilotage quotidien, contrôle qualité des données', bold=True)

heading('Workflow quotidien', level=2, color=DARK)
for prefix, text in [
    ('Matin : ', 'Vérifie productions du jour et stocks disponibles'),
    ('Paramétrage : ', 'Gère produits, fournisseurs, emplacements, catégories charges'),
    ('Contrôle : ', 'Vérifie les saisies des agents et commerciaux'),
    ('Validation : ', 'Valide les transferts inter-sites (Site principal ↔ Belleville)'),
    ('Suivi : ', 'Suit charges/dépenses et caisse'),
    ('Clôture : ', 'Rapproche la caisse en fin de journée'),
]:
    numbered(text, bold_prefix=prefix)

para('Droits : CRUD complet sans suppression sur toutes données métier. Consultation mouvements stock.')

# ══════════════════════════════════════════════════════════
# 4. GESTIONNAIRE STOCK
# ══════════════════════════════════════════════════════════
heading('4. Gestionnaire Stock & Hygiène — Flux physiques')
para('Vision : Maîtrise des flux physiques et traçabilité', bold=True)

heading('Workflow quotidien', level=2, color=DARK)

para('A. Réception des lots', bold=True)
bullet('Enregistre arrivages (poulets/œufs), saisit qté reçue, PM, IR, prix, transport')
bullet('Système calcule : quantité utilisable, coût total, coût moyen unitaire')
bullet('Attache la pièce jointe (facture/BL)')

para('B. Suivi factures fournisseurs', bold=True)
bullet('Marque règlements : non réglée → partielle → réglée + mode et date')

para('C. Mouvements de stock', bold=True)
bullet('Consulte entrées/sorties en temps réel par produit et emplacement')

para('D. Transferts inter-sites', bold=True)
bullet('Prépare envois Site → Belleville, saisit qtés. À réception : confirme qtés, calcule écarts')
bullet('Auto : sortie stock source + entrée stock destination')

para('E. Inventaire', bold=True)
bullet('Lance inventaire, saisit qtés physiques. Système affiche stock théorique vs physique → écart')
bullet('Justification obligatoire si écart. Validation → ajustement auto du stock')

para('F. Caisse', bold=True)
bullet('Enregistre caisse journalière : encaissé, crédits, décaissements, versements (espèces/Wave/MTN/chèques)')
bullet('Système calcule solde et écart. Clôture journalière obligatoire')

heading('Dashboard Stock', level=2, color=DARK)
bullet('Qté et valeur par produit/emplacement • Alertes rupture • Rotation lente • Historique mouvements')

para('Droits : CRUD lots, transferts, inventaires, stock, caisse. Consultation : productions, ventes, produits, fournisseurs.')

# ══════════════════════════════════════════════════════════
# 5. AGENT PRODUCTION
# ══════════════════════════════════════════════════════════
heading('5. Agent de Production — Saisie terrain')
para('Vision : Saisie rapide et fiable de la production quotidienne', bold=True)

heading('Workflow quotidien', level=2, color=DARK)
numbered('Consulte le lot du jour (poulets disponibles)', bold_prefix='Lot : ')
numbered('Crée fiche production liée au lot source', bold_prefix='Création : ')
numbered('Saisit nb poulets traités, poids moyen/total entrant', bold_prefix='En-tête : ')
numbered('Saisit lignes de production : escalopes, cuisses, ailes, gésiers, foies, carcasses, pattes... → qté en kg/unités', bold_prefix='Lignes : ')
numbered('Saisit pertes et casse en kg', bold_prefix='Pertes : ')
numbered('Soumet → statut « en_cours ». Un responsable valide → statut « validée »', bold_prefix='Validation : ')

para('Lors de la validation, le système déclenche automatiquement :')
bullet('Création mouvements stock (entrée_production) pour chaque ligne')
bullet('MAJ stock_emplacements + calcul rendement (sorties valorisées / coût lot)')

heading('Contrôle métier', level=2, color=RED)
para('Interdit de consommer plus de poulets que la quantité utilisable du lot.')

para('Module complémentaire : Eau en sachets (paquets produits, consommation sachets/énergie)', bold=True)

heading('Dashboard Production', level=2, color=DARK)
bullet('Volumes vs objectifs • Rendement par lot • Pertes/casse • Historique')

para('Droits : CRUD productions, eau. Consultation : lots, produits, stock, emplacements.')

# ══════════════════════════════════════════════════════════
# 6. COMMERCIAL / POINT DE VENTE
# ══════════════════════════════════════════════════════════
heading('6. Commercial / Point de vente — Vente & encaissement')
para('Vision : Vente rapide, suivi clients, encaissement efficace', bold=True)

heading('Workflow de vente', level=2, color=DARK)

para('1. Création : sélectionne/crée client, choisit canal (boutique, commercial, livraison, B2B), emplacement', bold=True)
para('2. Lignes : ajoute produits (qté × prix = montant auto). Vérifie stock (bloque si insuffisant). Remise possible. Montant net = total - remise', bold=True)
para('3. Encaissement : mode paiement (espèces, Wave, MTN, chèque, virement) + montant reçu', bold=True)

make_table(
    ['Condition', 'Statut', 'Description'],
    [
        ('Montant reçu = montant net', 'payé', 'Vente entièrement réglée'),
        ('Reçu > 0 et < net', 'partiel', 'Paiement partiel'),
        ('Reçu = 0', 'crédit', 'Vente à crédit'),
        ('Prise en charge patronne', 'réglé_patronne', 'La patronne règle'),
    ]
)

para('4. Validation → triggers auto :', bold=True)
bullet('Sortie stock (sortie_vente) + MAJ stock/CMP + calcul marge ligne + impact caisse + reçu PDF')

para('5. Suivi impayés :', bold=True)
bullet('Visualisation crédits/partiels, relance clients, paiements partiels → statut auto « payé » quand solde = 0')

heading('Commercial vs Point de vente', level=2, color=DARK)
make_table(
    ['Fonctionnalité', 'Commercial', 'Point de vente'],
    [
        ('Ventes / Clients / Paiements (CRUD)', '✓', '✓'),
        ('Consultation produits / stock / emplacements', '✓', '✓'),
        ('Consultation mouvements stock', '✓', '✗'),
        ('Consultation caisse', '✓', '✓'),
        ('Canaux de vente', 'Tous', 'Boutique'),
    ]
)

heading('Dashboard Commercial', level=2, color=DARK)
bullet('CA par commercial • Clients actifs • Impayés par commercial • Performance vs objectif')

# ══════════════════════════════════════════════════════════
# 7. FLUX MÉTIER PRINCIPAUX
# ══════════════════════════════════════════════════════════
heading('7. Flux métier principaux')

heading('Flux 1 : Achats → Production → Stock', level=2, color=DARK)
make_table(
    ['#', 'Action', 'Acteur', 'Tables'],
    [
        ('1', 'Réception lot (poulets/œufs)', 'Gest. Stock', 'lots'),
        ('2', 'Saisie production (abattage/découpe)', 'Agent Prod.', 'productions, production_lignes'),
        ('3', 'Validation production', 'Resp. Ops / Gérant', 'mouvements_stock, stock_emplacements'),
    ]
)

heading('Flux 2 : Stock → Vente → Caisse', level=2, color=DARK)
make_table(
    ['#', 'Action', 'Acteur', 'Tables'],
    [
        ('1', 'Saisie vente (client, produits, qtés, prix)', 'Commercial', 'ventes, vente_lignes'),
        ('2', 'Encaissement', 'Commercial', 'paiements'),
        ('3', 'Sortie stock auto', 'Système', 'mouvements_stock, stock_emplacements'),
        ('4', 'Impact caisse', 'Système', 'caisses'),
    ]
)

heading('Flux 3 : Transfert inter-sites', level=2, color=DARK)
make_table(
    ['#', 'Action', 'Acteur', 'Tables'],
    [
        ('1', 'Création transfert (Site → Belleville)', 'Gest. Stock (source)', 'transferts, transfert_lignes'),
        ('2', 'Expédition → sortie stock source', 'Système', 'mouvements_stock'),
        ('3', 'Réception → entrée stock destination', 'Gest. Stock (dest.)', 'mouvements_stock, stock_emplacements'),
    ]
)

heading('Flux 4 : Charges → Trésorerie', level=2, color=DARK)
make_table(
    ['#', 'Action', 'Acteur', 'Tables'],
    [
        ('1', 'Saisie charge/dépense', 'Resp. Ops', 'charges'),
        ('2', 'Impact caisse → décaissement', 'Système', 'caisses'),
    ]
)

# ══════════════════════════════════════════════════════════
# 8. MATRICE DES ACCÈS
# ══════════════════════════════════════════════════════════
heading('8. Matrice des accès par rôle')
para('Légende : C = Créer, L = Lire, M = Modifier, S = Supprimer, — = Aucun accès')

make_table(
    ['Module', 'Gérant', 'Resp. Ops', 'Gest. Stock', 'Agent Prod.', 'Commercial', 'Pt de vente'],
    [
        ('Lots / Achats', 'CLMS', 'CLM', 'CLM', 'L', '—', '—'),
        ('Productions', 'CLMS', 'CLM', 'L', 'CLM', '—', '—'),
        ('Produits', 'CLMS', 'CLM', 'L', 'L', 'L', 'L'),
        ('Fournisseurs', 'CLMS', 'CLM', 'L', '—', '—', '—'),
        ('Emplacements', 'CLMS', 'CLM', 'L', 'L', 'L', 'L'),
        ('Stock emplacements', 'CLM', 'CLM', 'CLM', 'L', 'L', 'L'),
        ('Mouvements stock', 'L', 'L', 'L', 'L', 'L', '—'),
        ('Transferts', 'CLMS', 'CLM', 'CLM', '—', '—', '—'),
        ('Inventaires', 'CLMS', 'CLM', 'CLM', '—', '—', '—'),
        ('Ventes', 'CLMS', 'CLM', 'L', '—', 'CLM', 'CLM'),
        ('Clients', 'CLMS', 'CLM', '—', '—', 'CLM', 'CLM'),
        ('Paiements', 'CLMS', 'CLM', '—', '—', 'CLM', 'CLM'),
        ('Caisses', 'CLMS', 'CLM', 'CLM', '—', 'L', 'L'),
        ('Charges', 'CLMS', 'CLM', 'L', '—', '—', '—'),
        ('Catégories charges', 'CLMS', 'CLM', '—', '—', '—', '—'),
        ('Eau (production)', 'CLMS', 'CLM', '—', 'CLM', '—', '—'),
        ('Utilisateurs/Rôles', '—', '—', '—', '—', '—', '—'),
    ]
)

p = doc.add_paragraph()
p.add_run('Note : ').bold = True
p.add_run('La gestion des utilisateurs et rôles est exclusivement réservée au super_admin.')

# ══════════════════════════════════════════════════════════
# ANNEXE : FORMULES MÉTIER
# ══════════════════════════════════════════════════════════
heading('Annexe : Formules métier clés')

formules = [
    ('Lots / Achats', [
        'coût_total = (prix_unit. × qté_reçue) + transport + autres_coûts',
        'qté_utilisable = qté_reçue - morts - refusés',
        'coût_moyen_unit. = coût_total / qté_utilisable',
    ]),
    ('Stock (CMP)', [
        'stock_théorique = initial + entrées - sorties ± transferts',
        'CMP = (ancien_stock × ancien_CMP + entrées × coût) / (ancien_stock + entrées)',
    ]),
    ('Ventes', ['montant_net = total - remise', 'montant_restant = net - reçu']),
    ('Marge', [
        'marge_ligne = montant_ligne - (qté × CMP)',
        'marge_vente = Σ(marge_ligne)',
        'marge_lot = Σ(ventes du lot) - coût_total lot',
    ]),
    ('Caisse', [
        'solde = encaissé + crédits_encaissés - décaissé',
        'écart = solde - (versé + wave_mtn + chèque)',
    ]),
]

for title, formulas in formules:
    heading(title, level=2, color=DARK)
    for f in formulas:
        p = doc.add_paragraph()
        r = p.add_run(f)
        r.font.name = 'Consolas'
        r.font.size = Pt(8)

# ── Sauvegarde ──
output_path = '/Applications/XAMPP/xamppfiles/htdocs/chapchap/WORKFLOW_CHAPCHAP.docx'
doc.save(output_path)
print(f'Document généré : {output_path}')
