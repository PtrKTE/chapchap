#!/usr/bin/env python3
"""
Génère la présentation CHAPCHAP au format PPTX.
Usage : python3 generate_pptx.py
"""

from pptx import Presentation
from pptx.util import Inches, Pt, Emu
from pptx.dml.color import RGBColor
from pptx.enum.text import PP_ALIGN, MSO_ANCHOR
from pptx.enum.shapes import MSO_SHAPE
import os

# === Chemin du logo ===
SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
LOGO_PATH = os.path.join(SCRIPT_DIR, 'public', 'images', 'Logo_chapchap.jpeg')

# === Couleurs ===
ORANGE = RGBColor(0xF9, 0x73, 0x16)
ORANGE_DARK = RGBColor(0xC2, 0x41, 0x0C)
DARK = RGBColor(0x1E, 0x29, 0x3B)
DARKER = RGBColor(0x0F, 0x17, 0x2A)
GRAY = RGBColor(0x64, 0x74, 0x8B)
WHITE = RGBColor(0xFF, 0xFF, 0xFF)
GREEN = RGBColor(0x22, 0xC5, 0x5E)
BLUE = RGBColor(0x3B, 0x82, 0xF6)
RED = RGBColor(0xEF, 0x44, 0x44)
LIGHT_BG = RGBColor(0xF8, 0xFA, 0xFC)
LIGHT_ORANGE = RGBColor(0xFF, 0xF7, 0xED)
LIGHT_GREEN = RGBColor(0xF0, 0xFD, 0xF4)
LIGHT_BLUE = RGBColor(0xEF, 0xF6, 0xFF)
LIGHT_RED = RGBColor(0xFE, 0xF2, 0xF2)

prs = Presentation()
prs.slide_width = Inches(13.333)
prs.slide_height = Inches(7.5)

SLIDE_W = Inches(13.333)
SLIDE_H = Inches(7.5)


# ======================================================================
# HELPERS
# ======================================================================

def add_bg(slide, color):
    bg = slide.background
    fill = bg.fill
    fill.solid()
    fill.fore_color.rgb = color


def add_rect(slide, left, top, width, height, fill_color, border_color=None):
    shape = slide.shapes.add_shape(MSO_SHAPE.ROUNDED_RECTANGLE, left, top, width, height)
    shape.fill.solid()
    shape.fill.fore_color.rgb = fill_color
    if border_color:
        shape.line.color.rgb = border_color
        shape.line.width = Pt(1.5)
    else:
        shape.line.fill.background()
    shape.shadow.inherit = False
    return shape


def add_text_box(slide, left, top, width, height, text, font_size=14,
                 color=DARK, bold=False, alignment=PP_ALIGN.LEFT, font_name='Calibri'):
    txBox = slide.shapes.add_textbox(left, top, width, height)
    tf = txBox.text_frame
    tf.word_wrap = True
    p = tf.paragraphs[0]
    p.text = text
    p.font.size = Pt(font_size)
    p.font.color.rgb = color
    p.font.bold = bold
    p.font.name = font_name
    p.alignment = alignment
    return txBox


def add_rich_text_box(slide, left, top, width, height):
    txBox = slide.shapes.add_textbox(left, top, width, height)
    tf = txBox.text_frame
    tf.word_wrap = True
    return tf


def add_slide_number(slide, text, top=Inches(0.5), left=Inches(0.8)):
    add_text_box(slide, left, top, Inches(4), Inches(0.35),
                 text.upper(), font_size=11, color=ORANGE, bold=True)


def add_title(slide, text, top=Inches(0.85), left=Inches(0.8)):
    add_text_box(slide, left, top, Inches(10), Inches(0.7),
                 text, font_size=32, color=DARK, bold=True)


def add_title_white(slide, text, top=Inches(0.85), left=Inches(0.8)):
    add_text_box(slide, left, top, Inches(10), Inches(0.7),
                 text, font_size=32, color=WHITE, bold=True)


def add_module_card(slide, left, top, width, height, emoji, title, description,
                    bg_color=WHITE, border=None):
    add_rect(slide, left, top, width, height, bg_color, border)
    add_text_box(slide, left + Inches(0.2), top + Inches(0.15), Inches(0.5), Inches(0.4),
                 emoji, font_size=22, color=DARK)
    add_text_box(slide, left + Inches(0.2), top + Inches(0.55), width - Inches(0.4), Inches(0.35),
                 title, font_size=15, color=DARK, bold=True)
    add_text_box(slide, left + Inches(0.2), top + Inches(0.9), width - Inches(0.4), height - Inches(1.1),
                 description, font_size=11, color=GRAY)


def add_bullet_list(slide, left, top, width, height, items, font_size=13,
                    color=DARK, bullet="→ ", bullet_color=ORANGE):
    tf = add_rich_text_box(slide, left, top, width, height)
    first = True
    for item in items:
        if first:
            p = tf.paragraphs[0]
            first = False
        else:
            p = tf.add_paragraph()
        run_b = p.add_run()
        run_b.text = bullet
        run_b.font.size = Pt(font_size)
        run_b.font.color.rgb = bullet_color
        run_b.font.bold = True
        run_b.font.name = 'Calibri'
        run_t = p.add_run()
        run_t.text = item
        run_t.font.size = Pt(font_size)
        run_t.font.color.rgb = color
        run_t.font.name = 'Calibri'
        p.space_after = Pt(3)
    return tf


def add_bottom_bar(slide):
    bar = slide.shapes.add_shape(MSO_SHAPE.RECTANGLE, 0, SLIDE_H - Inches(0.08), SLIDE_W, Inches(0.08))
    bar.fill.solid()
    bar.fill.fore_color.rgb = ORANGE
    bar.line.fill.background()


def add_logo_watermark(slide, left=None, top=None, width=Inches(0.6)):
    """Petit logo en coin de slide."""
    if left is None:
        left = SLIDE_W - Inches(1.2)
    if top is None:
        top = Inches(0.25)
    if os.path.exists(LOGO_PATH):
        slide.shapes.add_picture(LOGO_PATH, left, top, width=width)


def set_notes(slide, text):
    """Ajoute des notes de présentateur à la slide."""
    notes_slide = slide.notes_slide
    tf = notes_slide.notes_text_frame
    tf.text = text


# ======================================================================
# SLIDE 1 : COUVERTURE
# ======================================================================
slide = prs.slides.add_slide(prs.slide_layouts[6])
add_bg(slide, DARKER)

bar = slide.shapes.add_shape(MSO_SHAPE.RECTANGLE, 0, 0, SLIDE_W, Inches(0.06))
bar.fill.solid()
bar.fill.fore_color.rgb = ORANGE
bar.line.fill.background()

# Logo centré en grand
if os.path.exists(LOGO_PATH):
    logo_w = Inches(2.8)
    logo_x = (SLIDE_W - logo_w) // 2
    slide.shapes.add_picture(LOGO_PATH, logo_x, Inches(0.8), width=logo_w)

# Sous-titre
add_text_box(slide, Inches(0), Inches(3.7), SLIDE_W, Inches(0.5),
             "Application de Gestion Intégrée", font_size=26, color=WHITE,
             alignment=PP_ALIGN.CENTER)

add_text_box(slide, Inches(2), Inches(4.3), Inches(9.333), Inches(0.4),
             "Abattage  •  Découpe  •  Stock  •  Ventes  •  Trésorerie",
             font_size=16, color=GRAY, alignment=PP_ALIGN.CENTER)

sep = slide.shapes.add_shape(MSO_SHAPE.RECTANGLE, Inches(5.5), Inches(5.0), Inches(2.333), Inches(0.03))
sep.fill.solid()
sep.fill.fore_color.rgb = ORANGE
sep.line.fill.background()

add_text_box(slide, Inches(0), Inches(5.3), SLIDE_W, Inches(0.35),
             "Présentation Direction — Février 2026", font_size=14,
             color=GRAY, alignment=PP_ALIGN.CENTER)

# Mention discrète du porteur de projet
add_text_box(slide, Inches(0), Inches(5.75), SLIDE_W, Inches(0.3),
             "Conception, architecture et développement : Patrick TETCHI",
             font_size=12, color=RGBColor(0x55, 0x55, 0x77), alignment=PP_ALIGN.CENTER)

bar2 = slide.shapes.add_shape(MSO_SHAPE.RECTANGLE, 0, SLIDE_H - Inches(0.06), SLIDE_W, Inches(0.06))
bar2.fill.solid()
bar2.fill.fore_color.rgb = ORANGE
bar2.line.fill.background()

set_notes(slide, """Bonjour à toutes et à tous, merci d'être présents.

Aujourd'hui, je vais vous présenter CHAPCHAP, l'application de gestion intégrée que j'ai conçue et développée pour notre activité.

CHAPCHAP, c'est un outil sur mesure, pensé spécifiquement pour notre métier — de la réception des poulets vivants jusqu'à l'encaissement en caisse — et qui va transformer notre façon de travailler au quotidien.

Je vais vous montrer en détail ce que l'application fait, comment elle fonctionne, et où nous en sommes.""")


# ======================================================================
# SLIDE 2 : LE PROBLÈME
# ======================================================================
slide = prs.slides.add_slide(prs.slide_layouts[6])
add_bg(slide, LIGHT_BG)
add_slide_number(slide, "Contexte")
add_title(slide, "Le problème aujourd'hui")
add_bottom_bar(slide)
add_logo_watermark(slide)

# Colonne gauche
add_rect(slide, Inches(0.8), Inches(1.8), Inches(5.6), Inches(2.2), WHITE, RGBColor(0xE2, 0xE8, 0xF0))
left_bar = slide.shapes.add_shape(MSO_SHAPE.RECTANGLE, Inches(0.8), Inches(1.8), Inches(0.06), Inches(2.2))
left_bar.fill.solid()
left_bar.fill.fore_color.rgb = RED
left_bar.line.fill.background()

add_text_box(slide, Inches(1.1), Inches(1.9), Inches(5), Inches(0.35),
             "Suivi manuel et fragmenté", font_size=16, color=DARK, bold=True)
add_bullet_list(slide, Inches(1.1), Inches(2.3), Inches(5), Inches(1.5), [
    "Cahiers et fiches papier pour les lots, ventes, caisse",
    "Pas de visibilité temps réel sur le stock",
    "Difficulté à suivre les impayés clients",
    "Aucun calcul automatique des marges",
], font_size=12, color=GRAY)

add_rect(slide, Inches(0.8), Inches(4.2), Inches(5.6), Inches(2.0), WHITE, RGBColor(0xE2, 0xE8, 0xF0))
left_bar2 = slide.shapes.add_shape(MSO_SHAPE.RECTANGLE, Inches(0.8), Inches(4.2), Inches(0.06), Inches(2.0))
left_bar2.fill.solid()
left_bar2.fill.fore_color.rgb = RED
left_bar2.line.fill.background()

add_text_box(slide, Inches(1.1), Inches(4.3), Inches(5), Inches(0.35),
             "Risques identifiés", font_size=16, color=DARK, bold=True)
add_bullet_list(slide, Inches(1.1), Inches(4.65), Inches(5), Inches(1.3), [
    "Écarts de caisse non détectés",
    "Pertes de stock non tracées",
    "Crédits clients oubliés",
    "Pas de traçabilité des opérations",
], font_size=12, color=GRAY)

# Colonne droite
add_rect(slide, Inches(6.8), Inches(1.8), Inches(5.7), Inches(1.8), ORANGE)
add_text_box(slide, Inches(7.1), Inches(1.95), Inches(5.1), Inches(0.35),
             "L'objectif de CHAPCHAP", font_size=18, color=WHITE, bold=True)
add_text_box(slide, Inches(7.1), Inches(2.4), Inches(5.1), Inches(1.0),
             "Une application unique et centralisée qui connecte toute la chaîne :\nachat → production → stock → ventes → trésorerie",
             font_size=13, color=WHITE)

add_rect(slide, Inches(6.8), Inches(3.85), Inches(5.7), Inches(2.8), WHITE, RGBColor(0xE2, 0xE8, 0xF0))
add_text_box(slide, Inches(7.1), Inches(3.95), Inches(5.1), Inches(0.35),
             "Les bénéfices attendus", font_size=16, color=DARK, bold=True)
add_bullet_list(slide, Inches(7.1), Inches(4.35), Inches(5.1), Inches(2.1), [
    "Visibilité instantanée sur l'activité",
    "Calculs automatiques (stock, CMP, marges)",
    "Suivi des impayés et de la trésorerie",
    "Traçabilité complète de chaque opération",
    "Prise de décision basée sur les chiffres",
], font_size=12, color=GRAY)

set_notes(slide, """Avant de montrer la solution, revenons sur la situation actuelle.

Aujourd'hui, on fonctionne avec des cahiers, des fiches papier, et beaucoup de mémoire humaine. Le problème, c'est que ça ne nous donne aucune visibilité en temps réel. On ne sait pas exactement ce qu'on a en stock, on perd le fil des impayés, et quand il y a un écart de caisse, c'est difficile à tracer.

J'ai commencé par faire un audit complet de nos processus — j'ai passé du temps avec chaque équipe : production, commerciaux, caisse, stock — pour bien comprendre les besoins réels avant d'écrire une seule ligne de code.

L'objectif que j'ai défini est clair : une seule application qui connecte toute la chaîne, de l'achat du poulet vivant jusqu'à l'encaissement en caisse. Plus de ressaisie, plus de doublons, plus d'oublis.""")


# ======================================================================
# SLIDE 3 : CHAPCHAP EN CHIFFRES
# ======================================================================
slide = prs.slides.add_slide(prs.slide_layouts[6])
add_bg(slide, DARKER)
add_slide_number(slide, "Vue d'ensemble")
add_title_white(slide, "CHAPCHAP en chiffres")
add_logo_watermark(slide, top=Inches(0.15))

bar = slide.shapes.add_shape(MSO_SHAPE.RECTANGLE, 0, SLIDE_H - Inches(0.06), SLIDE_W, Inches(0.06))
bar.fill.solid()
bar.fill.fore_color.rgb = ORANGE
bar.line.fill.background()

card_w = Inches(2.6)
card_h = Inches(1.5)
gap = Inches(0.4)
start_x = Inches(0.8)
y1 = Inches(2.0)

stats_1 = [("9", "Modules métier"), ("23", "Tables en base"), ("10", "Widgets dashboard"), ("5", "Profils utilisateur")]
bg_c = RGBColor(0x1A, 0x1A, 0x2E)
for i, (num, label) in enumerate(stats_1):
    x = start_x + i * (card_w + gap)
    add_rect(slide, x, y1, card_w, card_h, bg_c, RGBColor(0x33, 0x33, 0x55))
    add_text_box(slide, x, y1 + Inches(0.2), card_w, Inches(0.6),
                 num, font_size=44, color=ORANGE, bold=True, alignment=PP_ALIGN.CENTER)
    add_text_box(slide, x, y1 + Inches(0.85), card_w, Inches(0.35),
                 label, font_size=13, color=GRAY, alignment=PP_ALIGN.CENTER)

card_w2 = Inches(3.6)
y2 = Inches(4.0)
stats_2 = [("27", "Tests automatisés\n100% réussis"), ("6", "Exports Excel"), ("2", "Rapports PDF")]
for i, (num, label) in enumerate(stats_2):
    x = start_x + i * (card_w2 + gap)
    add_rect(slide, x, y2, card_w2, card_h, bg_c, RGBColor(0x33, 0x33, 0x55))
    add_text_box(slide, x, y2 + Inches(0.15), card_w2, Inches(0.55),
                 num, font_size=38, color=ORANGE, bold=True, alignment=PP_ALIGN.CENTER)
    add_text_box(slide, x, y2 + Inches(0.8), card_w2, Inches(0.5),
                 label, font_size=12, color=GRAY, alignment=PP_ALIGN.CENTER)

add_text_box(slide, Inches(0), Inches(6.0), SLIDE_W, Inches(0.35),
             "Application web 100% sur mesure — accessible depuis navigateur, tablette et smartphone",
             font_size=12, color=GRAY, alignment=PP_ALIGN.CENTER)

set_notes(slide, """Voici CHAPCHAP en quelques chiffres.

L'application comprend 9 modules métier complets, couvrant l'intégralité de notre activité. Derrière, c'est 23 tables en base de données, chacune pensée pour refléter exactement notre façon de travailler.

Le tableau de bord affiche 10 widgets différents, adaptés à chaque profil — le gérant ne voit pas la même chose que le commercial ou l'agent de production. Chacun a les informations dont il a besoin.

Un point important : j'ai mis en place 27 tests automatisés qui vérifient en continu que les calculs critiques sont justes — le stock, les marges, les paiements. 100% de réussite. C'est ce qui garantit la fiabilité des données.

Et tout ça fonctionne dans un simple navigateur web, même sur téléphone. Pas besoin d'installer quoi que ce soit.""")


# ======================================================================
# SLIDE 4 : FLUX MÉTIER
# ======================================================================
slide = prs.slides.add_slide(prs.slide_layouts[6])
add_bg(slide, LIGHT_BG)
add_slide_number(slide, "Architecture métier")
add_title(slide, "Le flux complet")
add_bottom_bar(slide)
add_logo_watermark(slide)

flux_items = [
    ("🐔", "ACHATS", "Lots / Fournisseurs", RGBColor(0xFE, 0xF3, 0xC7), RGBColor(0xF5, 0x9E, 0x0B)),
    ("🔪", "PRODUCTION", "Abattage / Découpe", RGBColor(0xFC, 0xE7, 0xF3), RGBColor(0xEC, 0x48, 0x99)),
    ("📦", "STOCK", "Chambres froides", RGBColor(0xED, 0xE9, 0xFE), RGBColor(0x8B, 0x5C, 0xF6)),
    ("🛒", "VENTES", "Multi-canaux", RGBColor(0xD1, 0xFA, 0xE5), RGBColor(0x10, 0xB9, 0x81)),
    ("💰", "CAISSE", "Trésorerie", LIGHT_ORANGE, ORANGE),
]

box_w = Inches(1.8)
box_h = Inches(1.3)
arrow_w = Inches(0.4)
total = 5 * box_w.inches + 4 * arrow_w.inches
start_x_flux = (SLIDE_W.inches - total) / 2
y_flux = Inches(2.2)

for i, (emoji, title, desc, bg, border) in enumerate(flux_items):
    x = Inches(start_x_flux + i * (box_w.inches + arrow_w.inches))
    add_rect(slide, x, y_flux, box_w, box_h, bg, border)
    add_text_box(slide, x, y_flux + Inches(0.1), box_w, Inches(0.35),
                 emoji, font_size=24, alignment=PP_ALIGN.CENTER, color=DARK)
    add_text_box(slide, x, y_flux + Inches(0.45), box_w, Inches(0.3),
                 title, font_size=13, color=DARK, bold=True, alignment=PP_ALIGN.CENTER)
    add_text_box(slide, x, y_flux + Inches(0.75), box_w, Inches(0.3),
                 desc, font_size=10, color=GRAY, alignment=PP_ALIGN.CENTER)
    if i < 4:
        ax = Inches(start_x_flux + (i + 1) * box_w.inches + i * arrow_w.inches + 0.05)
        add_text_box(slide, ax, y_flux + Inches(0.35), Inches(0.3), Inches(0.4),
                     "→", font_size=22, color=ORANGE, bold=True, alignment=PP_ALIGN.CENTER)

add_rect(slide, Inches(0.8), Inches(4.0), Inches(5.6), Inches(2.8), WHITE, RGBColor(0xE2, 0xE8, 0xF0))
add_text_box(slide, Inches(1.1), Inches(4.1), Inches(5), Inches(0.35),
             "Automatisations clés", font_size=15, color=DARK, bold=True)
auto_items = [
    "✅  Stock mis à jour automatiquement à chaque vente",
    "✅  Coût Moyen Pondéré (CMP) recalculé en temps réel",
    "✅  Marges calculées automatiquement par produit",
    "✅  Statut factures fournisseurs auto-actualisé",
    "✅  Journal d'audit sur chaque action",
]
tf = add_rich_text_box(slide, Inches(1.1), Inches(4.5), Inches(5), Inches(2.1))
for i, item in enumerate(auto_items):
    if i == 0:
        p = tf.paragraphs[0]
    else:
        p = tf.add_paragraph()
    p.text = item
    p.font.size = Pt(12)
    p.font.color.rgb = GRAY
    p.font.name = 'Calibri'
    p.space_after = Pt(3)

add_rect(slide, Inches(6.8), Inches(4.0), Inches(5.7), Inches(2.8), WHITE, RGBColor(0xE2, 0xE8, 0xF0))
add_text_box(slide, Inches(7.1), Inches(4.1), Inches(5.1), Inches(0.35),
             "Transferts inter-sites", font_size=15, color=DARK, bold=True)

add_rect(slide, Inches(7.3), Inches(4.65), Inches(2.0), Inches(0.9), LIGHT_BLUE, BLUE)
add_text_box(slide, Inches(7.3), Inches(4.7), Inches(2.0), Inches(0.3),
             "🏭 Site principal", font_size=12, color=DARK, bold=True, alignment=PP_ALIGN.CENTER)
add_text_box(slide, Inches(7.3), Inches(5.0), Inches(2.0), Inches(0.3),
             "Abattoir + Chambre froide", font_size=10, color=GRAY, alignment=PP_ALIGN.CENTER)

add_text_box(slide, Inches(9.5), Inches(4.8), Inches(0.5), Inches(0.4),
             "⇄", font_size=22, color=ORANGE, bold=True, alignment=PP_ALIGN.CENTER)

add_rect(slide, Inches(10.2), Inches(4.65), Inches(2.0), Inches(0.9), LIGHT_GREEN, GREEN)
add_text_box(slide, Inches(10.2), Inches(4.7), Inches(2.0), Inches(0.3),
             "🏪 Belleville", font_size=12, color=DARK, bold=True, alignment=PP_ALIGN.CENTER)
add_text_box(slide, Inches(10.2), Inches(5.0), Inches(2.0), Inches(0.3),
             "Point de vente", font_size=10, color=GRAY, alignment=PP_ALIGN.CENTER)

add_text_box(slide, Inches(7.1), Inches(5.8), Inches(5.1), Inches(0.6),
             "Suivi des quantités envoyées vs reçues, avec gestion des écarts",
             font_size=11, color=GRAY, alignment=PP_ALIGN.CENTER)

set_notes(slide, """C'est le cœur de l'application : le flux métier complet.

J'ai conçu l'architecture pour que tout soit connecté de bout en bout. Quand on réceptionne un lot de poulets, le système enregistre le coût. Quand la production découpe, les produits obtenus entrent automatiquement en stock. Quand on vend, le stock sort automatiquement et la marge se calcule toute seule. Et en fin de journée, la caisse reflète exactement ce qui s'est passé.

Les 5 automatisations que vous voyez ici, c'est ce qui fait toute la différence avec le papier. Le CMP — le coût moyen pondéré — c'est un calcul complexe que j'ai implémenté pour qu'on connaisse en permanence le vrai coût de revient de chaque produit. C'est grâce à ça qu'on peut calculer les marges réelles.

J'ai aussi prévu les transferts entre nos deux sites — Site principal et Belleville — avec un suivi des quantités envoyées versus reçues, pour détecter les écarts.""")


# ======================================================================
# SLIDE 5 : LES 9 MODULES
# ======================================================================
slide = prs.slides.add_slide(prs.slide_layouts[6])
add_bg(slide, LIGHT_BG)
add_slide_number(slide, "Modules")
add_title(slide, "Les 9 modules de l'application")
add_bottom_bar(slide)
add_logo_watermark(slide)

modules = [
    ("🐔", "Lots & Achats", "Réception poulets/œufs, fournisseurs, coût moyen unitaire, factures"),
    ("🔪", "Production", "Abattage et découpe, rendement, pertes/casse, grille de rendement"),
    ("📦", "Stock", "Stock temps réel par emplacement, CMP, alertes, mouvements, inventaire"),
    ("🚚", "Transferts", "Transfert de produits entre sites, suivi envoi/réception, écarts"),
    ("🛒", "Ventes", "Multi-canaux, crédit, paiements partiels, reçus PDF"),
    ("👥", "Clients / CRM", "Fiche client, suivi commercial, historique, import Excel"),
    ("💰", "Caisse", "Journal quotidien, versements espèces/Wave/MTN/chèque, clôture"),
    ("📊", "Charges", "Dépenses par catégorie, impact caisse automatique"),
    ("💧", "Eau & Viande", "Modules complémentaires : eau en sachets, viande bovine"),
]

card_w = Inches(3.7)
card_h = Inches(1.5)
gap_x = Inches(0.35)
gap_y = Inches(0.2)
start_x = Inches(0.8)
start_y = Inches(1.8)

for i, (emoji, title, desc) in enumerate(modules):
    col = i % 3
    row = i // 3
    x = start_x + col * (card_w + gap_x)
    y = start_y + row * (card_h + gap_y)
    add_module_card(slide, x, y, card_w, card_h, emoji, title, desc)

set_notes(slide, """Voici les 9 modules que j'ai développés. Chacun couvre un pan complet de notre activité.

Le module Lots gère tout l'approvisionnement — réception, fournisseurs, facturation, et surtout le calcul automatique du coût unitaire. C'est la base de tout le reste.

La Production trace l'abattage et la découpe avec un suivi du rendement par lot. Ça nous permet de savoir exactement combien de kilos d'escalopes, de cuisses ou de gésiers on obtient par poulet.

Le module Stock, c'est le nerf de la guerre — stock en temps réel sur chaque site, avec le CMP, les alertes de stock bas, et un historique complet de chaque mouvement.

Les Ventes gèrent les 4 canaux : boutique, commerciaux terrain, livraison et B2B. Avec la gestion du crédit et des paiements partiels, adaptée à notre réalité terrain.

Chaque module a été pensé en partant de vos habitudes de travail, pas l'inverse.""")


# ======================================================================
# SLIDE 6 : DASHBOARD KPI
# ======================================================================
slide = prs.slides.add_slide(prs.slide_layouts[6])
add_bg(slide, DARKER)
add_slide_number(slide, "Tableau de bord")
add_title_white(slide, "Des KPI en temps réel pour chaque profil")
add_logo_watermark(slide, top=Inches(0.15))

bar = slide.shapes.add_shape(MSO_SHAPE.RECTANGLE, 0, SLIDE_H - Inches(0.06), SLIDE_W, Inches(0.06))
bar.fill.solid()
bar.fill.fore_color.rgb = ORANGE
bar.line.fill.background()

profiles = [
    ("👔 Vue Gérant", [
        "CA jour / semaine / mois",
        "Marge brute globale",
        "Évolution CA 30 jours",
        "Top 5 clients par CA",
        "Top 5 produits vendus",
        "Impayés et ancienneté",
        "Trésorerie 7 jours",
    ]),
    ("📦 Vue Stock", [
        "Stock total valorisé",
        "Alertes de stock bas",
        "Répartition par catégorie",
    ]),
    ("🏭 Vue Production", [
        "Poulets traités (sem/mois)",
        "Rendement moyen",
        "Pertes et casse (kg)",
    ]),
    ("🛒 Vue Commercial", [
        "CA par commercial",
        "Nb ventes et clients actifs",
        "Impayés par commercial",
        "Classement performance",
    ]),
]

card_w = Inches(2.8)
card_h = Inches(3.8)
gap = Inches(0.35)
start_x = Inches(0.8)
y_cards = Inches(2.0)
bg_card = RGBColor(0x1A, 0x1A, 0x2E)

for i, (title, items) in enumerate(profiles):
    x = start_x + i * (card_w + gap)
    add_rect(slide, x, y_cards, card_w, card_h, bg_card, RGBColor(0x33, 0x33, 0x55))
    add_text_box(slide, x + Inches(0.2), y_cards + Inches(0.15), card_w - Inches(0.4), Inches(0.35),
                 title, font_size=14, color=ORANGE, bold=True)
    tf = add_rich_text_box(slide, x + Inches(0.2), y_cards + Inches(0.6),
                           card_w - Inches(0.4), card_h - Inches(0.8))
    for j, item in enumerate(items):
        if j == 0:
            p = tf.paragraphs[0]
        else:
            p = tf.add_paragraph()
        p.text = "  " + item
        p.font.size = Pt(11)
        p.font.color.rgb = RGBColor(0xAA, 0xAA, 0xCC)
        p.font.name = 'Calibri'
        p.space_after = Pt(4)

add_text_box(slide, Inches(0), Inches(6.2), SLIDE_W, Inches(0.35),
             "10 widgets au total — chaque profil ne voit que les données qui le concernent",
             font_size=12, color=GRAY, alignment=PP_ALIGN.CENTER)

set_notes(slide, """Le tableau de bord, c'est la première chose que chaque utilisateur voit en se connectant. Et j'ai fait en sorte que ce soit immédiatement utile.

Pour la direction : le CA du jour, de la semaine, du mois, avec la tendance par rapport à la période précédente. La marge brute en temps réel. Les 5 meilleurs clients, les 5 produits les plus vendus. Et surtout, les impayés — combien, depuis combien de temps, chez quels clients.

Pour le stock : la situation en un coup d'œil avec les alertes quand un produit passe sous le seuil.

Pour la production : les volumes traités, le rendement, les pertes.

Pour les commerciaux : leur classement, leur CA, leurs impayés.

J'ai optimisé les requêtes pour que le dashboard se charge en moins de 2 secondes, même avec beaucoup de données. On est passé de 60 requêtes à 2-3 grâce au travail d'optimisation que j'ai fait.""")


# ======================================================================
# SLIDE 7 : PRODUITS GÉRÉS
# ======================================================================
slide = prs.slides.add_slide(prs.slide_layouts[6])
add_bg(slide, LIGHT_BG)
add_slide_number(slide, "Catalogue")
add_title(slide, "Les 17 produits gérés")
add_bottom_bar(slide)
add_logo_watermark(slide)

add_rect(slide, Inches(0.8), Inches(1.8), Inches(5.6), Inches(1.2), WHITE, RGBColor(0xE2, 0xE8, 0xF0))
add_text_box(slide, Inches(1.1), Inches(1.9), Inches(3), Inches(0.3),
             "🐔 Volaille entière", font_size=14, color=DARK, bold=True)
add_text_box(slide, Inches(1.1), Inches(2.25), Inches(5), Inches(0.4),
             "Poulet effilé  •  Poulet PAC  •  Coquelets  •  Poulets Morts",
             font_size=12, color=GRAY)

add_rect(slide, Inches(0.8), Inches(3.15), Inches(5.6), Inches(1.4), WHITE, RGBColor(0xE2, 0xE8, 0xF0))
add_text_box(slide, Inches(1.1), Inches(3.25), Inches(3), Inches(0.3),
             "🍗 Découpes", font_size=14, color=DARK, bold=True)
add_text_box(slide, Inches(1.1), Inches(3.6), Inches(5), Inches(0.7),
             "Escalopes (3 800 F/kg)  •  Cuisses (2 500 F/kg)  •  Ailes (2 000 F/kg)\nCarcasses (1 000 F/kg)  •  Brochettes  •  Assortiment",
             font_size=12, color=GRAY)

add_rect(slide, Inches(0.8), Inches(4.7), Inches(5.6), Inches(1.2), WHITE, RGBColor(0xE2, 0xE8, 0xF0))
add_text_box(slide, Inches(1.1), Inches(4.8), Inches(3), Inches(0.3),
             "🫀 Abats", font_size=14, color=DARK, bold=True)
add_text_box(slide, Inches(1.1), Inches(5.15), Inches(5), Inches(0.4),
             "Gésiers  •  Foies  •  Pattes  •  Cous  •  Intestins  •  Peaux",
             font_size=12, color=GRAY)

# Grille rendement
add_rect(slide, Inches(6.8), Inches(1.8), Inches(5.7), Inches(2.5), WHITE, RGBColor(0xE2, 0xE8, 0xF0))
add_text_box(slide, Inches(7.1), Inches(1.9), Inches(5), Inches(0.3),
             "📏 Grille de rendement intégrée", font_size=14, color=DARK, bold=True)

rendements = [
    ("Poids poulet", "Valorisation"),
    ("1,8 kg", "4 149 FCFA"),
    ("2,0 kg", "4 311 FCFA"),
    ("2,3 kg", "5 075 FCFA"),
]
tf = add_rich_text_box(slide, Inches(7.3), Inches(2.35), Inches(5), Inches(1.6))
for i, (col1, col2) in enumerate(rendements):
    if i == 0:
        p = tf.paragraphs[0]
    else:
        p = tf.add_paragraph()
    run1 = p.add_run()
    run1.text = f"  {col1:<20}"
    run1.font.size = Pt(13)
    run1.font.color.rgb = DARK if i == 0 else GRAY
    run1.font.bold = (i == 0)
    run1.font.name = 'Consolas'
    run2 = p.add_run()
    run2.text = col2
    run2.font.size = Pt(13)
    run2.font.color.rgb = DARK if i == 0 else ORANGE
    run2.font.bold = True
    run2.font.name = 'Consolas'
    p.space_after = Pt(4)

add_text_box(slide, Inches(7.1), Inches(3.6), Inches(5.1), Inches(0.4),
             "Calcule la valeur théorique de chaque poulet selon son poids",
             font_size=10, color=GRAY)

# Clients
add_rect(slide, Inches(6.8), Inches(4.5), Inches(5.7), Inches(1.9), WHITE, RGBColor(0xE2, 0xE8, 0xF0))
add_text_box(slide, Inches(7.1), Inches(4.6), Inches(5), Inches(0.3),
             "👥 Clients types gérés", font_size=14, color=DARK, bold=True)
add_text_box(slide, Inches(7.1), Inches(4.95), Inches(5.1), Inches(1.2),
             "Restaurants  •  Maquis  •  Hôtels  •  Supermarchés  •  Écoles\n\nClients B2B : SITA, Casino Prima, Hyper Hayat, NORAL, PROSUMA",
             font_size=12, color=GRAY)

set_notes(slide, """L'application gère nos 17 produits avec leurs prix de vente par défaut. Du poulet entier aux abats en passant par toutes les découpes.

Un point que je voudrais souligner : la grille de rendement. C'est un outil que j'ai intégré et qui permet de connaître la valeur théorique d'un poulet en fonction de son poids. Par exemple, un poulet de 2 kg, une fois découpé, donne des escalopes, des cuisses, des ailes, des carcasses, des abats — et la somme de tout ça vaut environ 4 311 francs. C'est un indicateur clé pour évaluer la rentabilité de la production.

Côté clients, l'application gère tous nos types de clientèle — du maquis du quartier aux grands comptes comme SITA ou PROSUMA — chacun avec son profil, son historique, et ses conditions.""")


# ======================================================================
# SLIDE 8 : SÉCURITÉ & PROFILS
# ======================================================================
slide = prs.slides.add_slide(prs.slide_layouts[6])
add_bg(slide, LIGHT_BG)
add_slide_number(slide, "Sécurité")
add_title(slide, "5 profils avec droits différenciés")
add_bottom_bar(slide)
add_logo_watermark(slide)

profiles_data = [
    ("👔 Gérant", "Global", "Vision complète, KPI, validation, exports, tous les modules"),
    ("⚙️ Resp. Opérations", "Global", "Pilotage quotidien, paramétrage, contrôle, validation"),
    ("📦 Gest. Stock", "Stock / Lots", "Mouvements stock, inventaires, lots, contrôle caisse"),
    ("🔪 Agent Production", "Production", "Saisie production, observations, suivi rendement"),
    ("🛒 Commercial", "Ventes", "Saisie ventes, clients, encaissements, suivi impayés"),
]

y_start = Inches(1.8)
row_h = Inches(0.55)

add_rect(slide, Inches(0.8), y_start, Inches(11.7), row_h, RGBColor(0xF1, 0xF5, 0xF9))
add_text_box(slide, Inches(1.0), y_start + Inches(0.1), Inches(2.5), Inches(0.3),
             "PROFIL", font_size=11, color=GRAY, bold=True)
add_text_box(slide, Inches(3.8), y_start + Inches(0.1), Inches(1.5), Inches(0.3),
             "PÉRIMÈTRE", font_size=11, color=GRAY, bold=True)
add_text_box(slide, Inches(5.6), y_start + Inches(0.1), Inches(6.5), Inches(0.3),
             "ACCÈS", font_size=11, color=GRAY, bold=True)

for i, (profil, scope, access) in enumerate(profiles_data):
    y = y_start + (i + 1) * row_h
    if i % 2 == 0:
        add_rect(slide, Inches(0.8), y, Inches(11.7), row_h, WHITE)
    add_text_box(slide, Inches(1.0), y + Inches(0.1), Inches(2.5), Inches(0.3),
                 profil, font_size=13, color=DARK, bold=True)
    add_text_box(slide, Inches(3.8), y + Inches(0.1), Inches(1.5), Inches(0.3),
                 scope, font_size=12, color=ORANGE, bold=True)
    add_text_box(slide, Inches(5.6), y + Inches(0.1), Inches(6.5), Inches(0.3),
                 access, font_size=12, color=GRAY)

sec_items = [
    ("🔒", "Mots de passe hachés", "Bcrypt 12 rounds — impossible à retrouver"),
    ("📝", "Journal d'audit complet", "Qui a fait quoi, quand, depuis quelle IP"),
    ("🔐", "Sessions chiffrées", "HTTPS obligatoire, protection CSRF & XSS"),
]
for i, (emoji, title, desc) in enumerate(sec_items):
    x = Inches(0.8) + i * Inches(4.05)
    y = Inches(5.3)
    add_rect(slide, x, y, Inches(3.9), Inches(1.1), WHITE, RGBColor(0xE2, 0xE8, 0xF0))
    add_text_box(slide, x + Inches(0.2), y + Inches(0.1), Inches(3.5), Inches(0.3),
                 f"{emoji}  {title}", font_size=14, color=DARK, bold=True)
    add_text_box(slide, x + Inches(0.2), y + Inches(0.5), Inches(3.5), Inches(0.4),
                 desc, font_size=12, color=GRAY)

set_notes(slide, """La sécurité est un sujet que j'ai traité dès le départ. J'ai mis en place un système de 5 profils avec des droits strictement cloisonnés.

Un commercial ne peut pas modifier le stock. Un agent de production ne peut pas toucher aux ventes. Et seuls le gérant et le responsable des opérations ont accès aux données financières complètes.

Trois mesures de sécurité importantes : les mots de passe sont hachés avec Bcrypt — même si quelqu'un accède à la base, il ne peut pas retrouver les mots de passe. Le journal d'audit enregistre chaque action — chaque création, modification, suppression est tracée avec l'utilisateur, la date et l'adresse IP. Et toutes les communications sont chiffrées en HTTPS.

C'est un niveau de sécurité professionnel, pas un bricolage.""")


# ======================================================================
# SLIDE 9 : EXPORTS & RAPPORTS
# ======================================================================
slide = prs.slides.add_slide(prs.slide_layouts[6])
add_bg(slide, DARKER)
add_slide_number(slide, "Rapports")
add_title_white(slide, "Exports Excel et rapports PDF")
add_logo_watermark(slide, top=Inches(0.15))

bar = slide.shapes.add_shape(MSO_SHAPE.RECTANGLE, 0, SLIDE_H - Inches(0.06), SLIDE_W, Inches(0.06))
bar.fill.solid()
bar.fill.fore_color.rgb = ORANGE
bar.line.fill.background()

bg_card = RGBColor(0x1A, 0x1A, 0x2E)

add_rect(slide, Inches(0.8), Inches(2.0), Inches(5.6), Inches(4.0), bg_card, RGBColor(0x33, 0x33, 0x55))
add_text_box(slide, Inches(1.1), Inches(2.15), Inches(5), Inches(0.35),
             "📊  6 Exports Excel", font_size=16, color=GREEN, bold=True)

exports = [
    ("Ventes", "Filtrable par dates"),
    ("Lots / Achats", "Filtrable par dates"),
    ("Clients", "Tous les clients actifs"),
    ("Charges", "Filtrable par dates"),
    ("Caisses", "Filtrable par dates"),
    ("Stock", "Photo instantanée"),
]
tf = add_rich_text_box(slide, Inches(1.1), Inches(2.65), Inches(5), Inches(2.5))
for i, (name, desc) in enumerate(exports):
    if i == 0:
        p = tf.paragraphs[0]
    else:
        p = tf.add_paragraph()
    run1 = p.add_run()
    run1.text = f"  {name:<20}"
    run1.font.size = Pt(13)
    run1.font.color.rgb = WHITE
    run1.font.bold = True
    run1.font.name = 'Calibri'
    run2 = p.add_run()
    run2.text = desc
    run2.font.size = Pt(12)
    run2.font.color.rgb = GRAY
    run2.font.name = 'Calibri'
    p.space_after = Pt(6)

add_text_box(slide, Inches(1.1), Inches(4.8), Inches(5), Inches(0.3),
             "📥  Import Excel", font_size=14, color=BLUE, bold=True)
add_text_box(slide, Inches(1.1), Inches(5.15), Inches(5), Inches(0.5),
             "Import de clients depuis fichier Excel avec validation automatique",
             font_size=12, color=GRAY)

add_rect(slide, Inches(6.8), Inches(2.0), Inches(5.7), Inches(4.0), bg_card, RGBColor(0x33, 0x33, 0x55))
add_text_box(slide, Inches(7.1), Inches(2.15), Inches(5), Inches(0.35),
             "📄  2 Rapports PDF", font_size=16, color=RED, bold=True)

add_rect(slide, Inches(7.1), Inches(2.7), Inches(5.1), Inches(1.2),
         RGBColor(0x22, 0x22, 0x3A), RGBColor(0x33, 0x33, 0x55))
add_text_box(slide, Inches(7.3), Inches(2.8), Inches(4.7), Inches(0.3),
             "Reçu de vente", font_size=14, color=WHITE, bold=True)
add_text_box(slide, Inches(7.3), Inches(3.15), Inches(4.7), Inches(0.5),
             "En-tête CHAPCHAP, client, produits, totaux, statut paiement",
             font_size=11, color=GRAY)

add_rect(slide, Inches(7.1), Inches(4.1), Inches(5.1), Inches(1.2),
         RGBColor(0x22, 0x22, 0x3A), RGBColor(0x33, 0x33, 0x55))
add_text_box(slide, Inches(7.3), Inches(4.2), Inches(4.7), Inches(0.3),
             "Rapport de caisse", font_size=14, color=WHITE, bold=True)
add_text_box(slide, Inches(7.3), Inches(4.55), Inches(4.7), Inches(0.5),
             "Résumé journalier : encaissements, décaissements, charges, solde, signatures",
             font_size=11, color=GRAY)

add_rect(slide, Inches(7.1), Inches(5.5), Inches(5.1), Inches(0.5), bg_card, ORANGE)
add_text_box(slide, Inches(7.1), Inches(5.55), Inches(5.1), Inches(0.35),
             "Tous les exports en 1 clic depuis l'interface",
             font_size=13, color=ORANGE, bold=True, alignment=PP_ALIGN.CENTER)

set_notes(slide, """Un outil n'est utile que si on peut en sortir les données facilement. J'ai développé 6 exports Excel et 2 rapports PDF.

Les exports Excel couvrent toutes les données clés : ventes, lots, clients, charges, caisses, et une photo du stock. Les ventes, lots, charges et caisses sont filtrables par période — on peut exporter les ventes du mois de janvier par exemple.

Côté PDF, on a le reçu de vente — imprimable pour le client — et le rapport de caisse journalier avec tout le détail des encaissements, décaissements, et les zones de signature.

J'ai aussi prévu l'import Excel pour charger rapidement des listes de clients existants dans le système, ce qui va nous faire gagner beaucoup de temps au démarrage.

Tout se fait en un clic, directement depuis l'interface.""")


# ======================================================================
# SLIDE 10 : FIABILITÉ & BACKUPS
# ======================================================================
slide = prs.slides.add_slide(prs.slide_layouts[6])
add_bg(slide, LIGHT_BG)
add_slide_number(slide, "Fiabilité")
add_title(slide, "Backups et continuité")
add_bottom_bar(slide)
add_logo_watermark(slide)

add_rect(slide, Inches(0.8), Inches(1.8), Inches(5.6), Inches(2.2), WHITE, RGBColor(0xE2, 0xE8, 0xF0))
left_bar = slide.shapes.add_shape(MSO_SHAPE.RECTANGLE, Inches(0.8), Inches(1.8), Inches(0.06), Inches(2.2))
left_bar.fill.solid()
left_bar.fill.fore_color.rgb = GREEN
left_bar.line.fill.background()

add_text_box(slide, Inches(1.1), Inches(1.9), Inches(5), Inches(0.3),
             "Sauvegardes automatiques", font_size=15, color=DARK, bold=True)
backup_items = [
    "Backup BDD .................. Tous les jours à 2h",
    "Backup complet ............. Chaque dimanche à 4h",
    "Nettoyage auto .............. Rétention 30 jours",
    "Monitoring ..................... Alerte si backup manquant",
]
tf = add_rich_text_box(slide, Inches(1.3), Inches(2.3), Inches(4.8), Inches(1.5))
for i, item in enumerate(backup_items):
    if i == 0:
        p = tf.paragraphs[0]
    else:
        p = tf.add_paragraph()
    p.text = item
    p.font.size = Pt(12)
    p.font.color.rgb = GRAY
    p.font.name = 'Consolas'
    p.space_after = Pt(3)

add_rect(slide, Inches(0.8), Inches(4.2), Inches(5.6), Inches(2.4), WHITE, RGBColor(0xE2, 0xE8, 0xF0))
left_bar2 = slide.shapes.add_shape(MSO_SHAPE.RECTANGLE, Inches(0.8), Inches(4.2), Inches(0.06), Inches(2.4))
left_bar2.fill.solid()
left_bar2.fill.fore_color.rgb = BLUE
left_bar2.line.fill.background()

add_text_box(slide, Inches(1.1), Inches(4.3), Inches(5), Inches(0.3),
             "Tests automatisés — Garantie qualité", font_size=15, color=DARK, bold=True)
add_text_box(slide, Inches(1.1), Inches(4.65), Inches(4), Inches(0.35),
             "27 tests — 72 vérifications — 100% OK", font_size=14, color=GREEN, bold=True)
add_bullet_list(slide, Inches(1.1), Inches(5.0), Inches(5), Inches(1.4), [
    "Calculs stock et CMP vérifiés",
    "Flux de vente complet simulé",
    "Paiements partiels et crédits testés",
    "Annulations avec reconstitution stock",
    "Calculs lots et factures fournisseur",
], font_size=11, color=GRAY)

add_rect(slide, Inches(6.8), Inches(1.8), Inches(5.7), Inches(2.2), WHITE, RGBColor(0xE2, 0xE8, 0xF0))
left_bar3 = slide.shapes.add_shape(MSO_SHAPE.RECTANGLE, Inches(6.8), Inches(1.8), Inches(0.06), Inches(2.2))
left_bar3.fill.solid()
left_bar3.fill.fore_color.rgb = ORANGE
left_bar3.line.fill.background()

add_text_box(slide, Inches(7.1), Inches(1.9), Inches(5), Inches(0.3),
             "Hébergement recommandé", font_size=15, color=DARK, bold=True)
add_bullet_list(slide, Inches(7.1), Inches(2.3), Inches(5.1), Inches(1.5), [
    "VPS : DigitalOcean ou Hetzner",
    "Déploiement : Laravel Forge (automatisé)",
    "SSL : Let's Encrypt (HTTPS gratuit)",
    "Coût estimé : ~15 000 FCFA / mois",
], font_size=12, color=GRAY)

add_rect(slide, Inches(6.8), Inches(4.2), Inches(5.7), Inches(2.4), WHITE, RGBColor(0xE2, 0xE8, 0xF0))
left_bar4 = slide.shapes.add_shape(MSO_SHAPE.RECTANGLE, Inches(6.8), Inches(4.2), Inches(0.06), Inches(2.4))
left_bar4.fill.solid()
left_bar4.fill.fore_color.rgb = RGBColor(0x8B, 0x5C, 0xF6)
left_bar4.line.fill.background()

add_text_box(slide, Inches(7.1), Inches(4.3), Inches(5), Inches(0.3),
             "Optimisations intégrées", font_size=15, color=DARK, bold=True)
add_bullet_list(slide, Inches(7.1), Inches(4.65), Inches(5.1), Inches(1.7), [
    "Mise en cache des configurations",
    "Requêtes optimisées (pas de N+1)",
    "Dashboard en 2-3 requêtes au lieu de 60+",
    "Compression des backups (gzip)",
], font_size=12, color=GRAY)

set_notes(slide, """La fiabilité, c'est ce qui sépare un vrai outil professionnel d'un bricolage.

Les sauvegardes sont entièrement automatisées. Chaque nuit à 2 heures, la base de données est sauvegardée. Chaque dimanche, un backup complet est fait. Si un backup échoue, une alerte est envoyée. Et les anciens backups sont nettoyés automatiquement. Je n'ai pas attendu la mise en production pour penser à ça — c'est intégré dès le départ.

Les 27 tests automatisés que j'ai écrits couvrent tous les calculs critiques. À chaque mise à jour de l'application, ces tests s'exécutent et vérifient que rien n'est cassé. C'est une pratique professionnelle que j'ai mise en place pour garantir la qualité dans la durée.

Côté hébergement, un VPS à 15 000 francs par mois suffit largement. Et j'ai optimisé les requêtes pour que tout reste fluide même quand les données grossissent.""")


# ======================================================================
# SLIDE 11 : AVANCEMENT
# ======================================================================
slide = prs.slides.add_slide(prs.slide_layouts[6])
add_bg(slide, DARKER)
add_slide_number(slide, "Planning")
add_title_white(slide, "Avancement du projet")
add_logo_watermark(slide, top=Inches(0.15))

bar = slide.shapes.add_shape(MSO_SHAPE.RECTANGLE, 0, SLIDE_H - Inches(0.06), SLIDE_W, Inches(0.06))
bar.fill.solid()
bar.fill.fore_color.rgb = ORANGE
bar.line.fill.background()

bg_card = RGBColor(0x1A, 0x1A, 0x2E)

add_rect(slide, Inches(0.8), Inches(2.0), Inches(6.0), Inches(4.8), bg_card, RGBColor(0x33, 0x33, 0x55))
add_text_box(slide, Inches(1.1), Inches(2.1), Inches(5.4), Inches(0.35),
             "✅  12 phases terminées", font_size=16, color=GREEN, bold=True)

phases = [
    ("S1", "Setup projet & architecture"),
    ("S2", "BDD, migrations, models, seeders"),
    ("S3", "Module Lots & Achats"),
    ("S4", "Module Production"),
    ("S5", "Module Stock & Transferts"),
    ("S6", "CRM, Ventes & Paiements"),
    ("S7", "Caisse & Charges"),
    ("S8", "Modules Eau & Viande"),
    ("S9", "Dashboards & KPI"),
    ("S10", "Imports / Exports Excel & PDF"),
    ("S11", "Tests & optimisation"),
    ("S12", "Déploiement & documentation"),
]
tf = add_rich_text_box(slide, Inches(1.1), Inches(2.55), Inches(5.4), Inches(4.0))
for i, (sem, desc) in enumerate(phases):
    if i == 0:
        p = tf.paragraphs[0]
    else:
        p = tf.add_paragraph()
    run1 = p.add_run()
    run1.text = f"  ● {sem} "
    run1.font.size = Pt(12)
    run1.font.color.rgb = GREEN
    run1.font.bold = True
    run1.font.name = 'Calibri'
    run2 = p.add_run()
    run2.text = f"— {desc}"
    run2.font.size = Pt(12)
    run2.font.color.rgb = RGBColor(0xAA, 0xAA, 0xCC)
    run2.font.name = 'Calibri'
    p.space_after = Pt(2)

# Prochaines étapes
add_rect(slide, Inches(7.2), Inches(2.0), Inches(5.3), Inches(2.5), bg_card, RGBColor(0x33, 0x33, 0x55))
add_text_box(slide, Inches(7.5), Inches(2.1), Inches(4.7), Inches(0.35),
             "🔜  Prochaines étapes (S13-14)", font_size=14, color=ORANGE, bold=True)

next_items = [
    "Formation des équipes sur site",
    "Ajustements UX selon retours terrain",
    "Import de l'historique existant",
    "Correction des bugs remontés",
    "Mise en production définitive",
]
tf = add_rich_text_box(slide, Inches(7.5), Inches(2.55), Inches(4.7), Inches(1.8))
for i, item in enumerate(next_items):
    if i == 0:
        p = tf.paragraphs[0]
    else:
        p = tf.add_paragraph()
    run = p.add_run()
    run.text = f"  → {item}"
    run.font.size = Pt(12)
    run.font.color.rgb = RGBColor(0xAA, 0xAA, 0xCC)
    run.font.name = 'Calibri'
    p.space_after = Pt(3)

# Barre de progression
add_rect(slide, Inches(7.2), Inches(4.8), Inches(5.3), Inches(2.0), bg_card, ORANGE)
add_text_box(slide, Inches(7.2), Inches(4.95), Inches(5.3), Inches(0.7),
             "12 / 14", font_size=48, color=ORANGE, bold=True, alignment=PP_ALIGN.CENTER)
add_text_box(slide, Inches(7.2), Inches(5.6), Inches(5.3), Inches(0.3),
             "semaines complétées", font_size=12, color=GRAY, alignment=PP_ALIGN.CENTER)

add_rect(slide, Inches(7.8), Inches(6.05), Inches(4.1), Inches(0.2), RGBColor(0x33, 0x33, 0x55))
add_rect(slide, Inches(7.8), Inches(6.05), Inches(3.5), Inches(0.2), ORANGE)

add_text_box(slide, Inches(7.2), Inches(6.3), Inches(5.3), Inches(0.3),
             "86% du projet réalisé", font_size=14, color=ORANGE, bold=True, alignment=PP_ALIGN.CENTER)

set_notes(slide, """Voici où en est le projet. Sur les 14 semaines prévues dans le planning initial, 12 sont terminées. On est à 86% d'avancement.

Je voudrais attirer votre attention sur un point : ce planning était ambitieux. 9 modules métier complets, des dashboards, des exports, des tests, la documentation, le déploiement — tout ça a été livré dans les délais que j'avais définis.

Les 12 phases vertes que vous voyez à gauche représentent un travail conséquent : l'analyse des besoins, la conception de la base de données, le développement de chaque module, les tests, l'optimisation, et la préparation au déploiement.

Il nous reste les phases 13 et 14 — la stabilisation terrain. C'est la période où on forme les équipes, on recueille les retours, et on ajuste. C'est une étape normale et prévue dans tout projet sérieux.

Je suis confiant sur le respect du calendrier final.""")


# ======================================================================
# SLIDE 12 : PLAN DE FORMATION
# ======================================================================
slide = prs.slides.add_slide(prs.slide_layouts[6])
add_bg(slide, LIGHT_BG)
add_slide_number(slide, "Mise en service")
add_title(slide, "Plan de formation")
add_bottom_bar(slide)
add_logo_watermark(slide)

add_rect(slide, Inches(0.8), Inches(1.8), Inches(5.6), Inches(1.8), WHITE, RGBColor(0xE2, 0xE8, 0xF0))
add_text_box(slide, Inches(1.1), Inches(1.9), Inches(5), Inches(0.3),
             "📚  Documentation livrée", font_size=15, color=DARK, bold=True)
add_bullet_list(slide, Inches(1.1), Inches(2.25), Inches(5), Inches(1.0), [
    "Guide utilisateur complet (toutes les fonctions)",
    "Guide de déploiement serveur (installation, config, backups)",
    "Cahier des charges technique détaillé",
], font_size=12, color=GRAY)

add_rect(slide, Inches(0.8), Inches(3.8), Inches(5.6), Inches(2.8), WHITE, RGBColor(0xE2, 0xE8, 0xF0))
add_text_box(slide, Inches(1.1), Inches(3.9), Inches(5), Inches(0.3),
             "🎯  Sessions de formation recommandées", font_size=15, color=DARK, bold=True)

sessions = [
    ("Session 1", "Gérant + Resp. Opérations", "Dashboard, KPI, validation, exports"),
    ("Session 2", "Gestionnaire Stock", "Lots, stock, transferts, inventaire"),
    ("Session 3", "Agent Production", "Saisie production, rendement"),
    ("Session 4", "Commerciaux", "Ventes, clients, encaissements, caisse"),
]
tf = add_rich_text_box(slide, Inches(1.1), Inches(4.3), Inches(5), Inches(2.0))
for i, (session, public, contenu) in enumerate(sessions):
    if i == 0:
        p = tf.paragraphs[0]
    else:
        p = tf.add_paragraph()
    run1 = p.add_run()
    run1.text = f"  {session}  "
    run1.font.size = Pt(12)
    run1.font.color.rgb = ORANGE
    run1.font.bold = True
    run1.font.name = 'Calibri'
    run2 = p.add_run()
    run2.text = f"{public}  —  "
    run2.font.size = Pt(12)
    run2.font.color.rgb = DARK
    run2.font.bold = True
    run2.font.name = 'Calibri'
    run3 = p.add_run()
    run3.text = contenu
    run3.font.size = Pt(11)
    run3.font.color.rgb = GRAY
    run3.font.name = 'Calibri'
    p.space_after = Pt(6)

add_rect(slide, Inches(6.8), Inches(1.8), Inches(5.7), Inches(2.5), WHITE, RGBColor(0xE2, 0xE8, 0xF0))
add_text_box(slide, Inches(7.1), Inches(1.9), Inches(5), Inches(0.3),
             "📱  Accessibilité", font_size=15, color=DARK, bold=True)
add_bullet_list(slide, Inches(7.1), Inches(2.25), Inches(5.1), Inches(1.8), [
    "100% web — rien à installer sur les postes",
    "Mobile-friendly — utilisable sur téléphone",
    "Multi-utilisateurs — accès simultané",
    "Interface légère — pas besoin de haut débit",
], font_size=12, color=GRAY)

add_rect(slide, Inches(6.8), Inches(4.5), Inches(5.7), Inches(1.5), ORANGE)
add_text_box(slide, Inches(7.1), Inches(4.65), Inches(5.1), Inches(0.35),
             "Commerciaux terrain", font_size=18, color=WHITE, bold=True)
add_text_box(slide, Inches(7.1), Inches(5.1), Inches(5.1), Inches(0.7),
             "Hector, Niamke, Jessy, Yves et Evelyne peuvent saisir\nles ventes directement depuis leur téléphone",
             font_size=13, color=WHITE)

# Note discrète sur la maintenance
add_rect(slide, Inches(6.8), Inches(6.2), Inches(5.7), Inches(0.5), WHITE, RGBColor(0xE2, 0xE8, 0xF0))
add_text_box(slide, Inches(7.1), Inches(6.25), Inches(5.1), Inches(0.35),
             "🛠  Support et maintenance assurés par Patrick TETCHI",
             font_size=11, color=GRAY)

set_notes(slide, """Pour la mise en service, j'ai préparé une documentation complète : un guide utilisateur qui explique chaque fonctionnalité pas à pas, et un guide technique pour le déploiement et la maintenance du serveur.

Je recommande 4 sessions de formation ciblées — une par profil. Pas besoin de former tout le monde à tout : chaque équipe apprend uniquement ce qui la concerne. C'est plus efficace et ça va plus vite.

Un point fort de l'application : elle est 100% web. Rien à installer sur les postes ou les téléphones. On ouvre le navigateur, on se connecte, et c'est parti. Les commerciaux sur le terrain — Hector, Niamke, Jessy, Yves, Evelyne — pourront saisir leurs ventes directement depuis leur téléphone. Plus besoin d'attendre le retour au bureau.

Je resterai disponible pour le support et la maintenance post-déploiement, pour accompagner la transition et traiter les remontées du terrain.""")


# ======================================================================
# SLIDE 13 : CONCLUSION
# ======================================================================
slide = prs.slides.add_slide(prs.slide_layouts[6])
add_bg(slide, DARKER)

bar = slide.shapes.add_shape(MSO_SHAPE.RECTANGLE, 0, 0, SLIDE_W, Inches(0.06))
bar.fill.solid()
bar.fill.fore_color.rgb = ORANGE
bar.line.fill.background()

# Logo centré
if os.path.exists(LOGO_PATH):
    logo_w = Inches(2.0)
    logo_x = (SLIDE_W - logo_w) // 2
    slide.shapes.add_picture(LOGO_PATH, logo_x, Inches(0.5), width=logo_w)

add_text_box(slide, Inches(2), Inches(2.7), Inches(9.333), Inches(0.8),
             "Une application complète, fiable et simple\npour piloter toute l'activité au quotidien.",
             font_size=22, color=WHITE, alignment=PP_ALIGN.CENTER)

flux_emojis = [
    ("🐔", "Achat"), ("🔪", "Découpe"), ("📦", "Stock"), ("🛒", "Vente"), ("💰", "Caisse")
]
box_size = Inches(1.3)
total_w = 5 * box_size.inches + 4 * 0.6
start_x = (SLIDE_W.inches - total_w) / 2
y_flux = Inches(3.8)

for i, (emoji, label) in enumerate(flux_emojis):
    x = Inches(start_x + i * (box_size.inches + 0.6))
    add_rect(slide, x, y_flux, box_size, box_size,
             RGBColor(0x1A, 0x1A, 0x2E), RGBColor(0x33, 0x33, 0x55))
    add_text_box(slide, x, y_flux + Inches(0.2), box_size, Inches(0.4),
                 emoji, font_size=28, alignment=PP_ALIGN.CENTER, color=WHITE)
    add_text_box(slide, x, y_flux + Inches(0.7), box_size, Inches(0.3),
                 label, font_size=11, color=GRAY, alignment=PP_ALIGN.CENTER)
    if i < 4:
        ax = Inches(start_x + (i + 1) * box_size.inches + i * 0.6 + 0.15)
        add_text_box(slide, ax, y_flux + Inches(0.35), Inches(0.3), Inches(0.4),
                     "→", font_size=20, color=ORANGE, bold=True, alignment=PP_ALIGN.CENTER)

add_text_box(slide, Inches(0), Inches(5.5), SLIDE_W, Inches(0.5),
             "Tout est connecté.  Tout est tracé.  Tout est automatisé.",
             font_size=16, color=GRAY, alignment=PP_ALIGN.CENTER)

# Crédit projet
add_text_box(slide, Inches(0), Inches(6.3), SLIDE_W, Inches(0.3),
             "Conception, développement et déploiement : Patrick TETCHI",
             font_size=13, color=RGBColor(0x55, 0x55, 0x77), alignment=PP_ALIGN.CENTER)

add_text_box(slide, Inches(0), Inches(6.65), SLIDE_W, Inches(0.3),
             "Merci de votre attention",
             font_size=14, color=RGBColor(0x44, 0x44, 0x66), alignment=PP_ALIGN.CENTER)

bar2 = slide.shapes.add_shape(MSO_SHAPE.RECTANGLE, 0, SLIDE_H - Inches(0.06), SLIDE_W, Inches(0.06))
bar2.fill.solid()
bar2.fill.fore_color.rgb = ORANGE
bar2.line.fill.background()

set_notes(slide, """Pour conclure.

CHAPCHAP, c'est une application complète, fiable, et surtout conçue pour notre métier. Ce n'est pas un logiciel générique qu'on a essayé d'adapter — c'est un outil sur mesure, pensé à partir de nos processus réels, de nos produits, de nos clients, de notre façon de travailler.

De l'achat du poulet vivant chez OVECH RANCH jusqu'à l'encaissement en caisse chez nos clients, tout est connecté, tout est tracé, tout est automatisé.

C'est un projet que j'ai porté de A à Z — de l'analyse initiale des besoins jusqu'au déploiement — et je suis fier de la qualité du résultat. L'application est prête. Il nous reste la formation et la stabilisation terrain, et nous serons pleinement opérationnels.

Je suis disponible pour vos questions.

Merci de votre attention.""")


# ======================================================================
# SAUVEGARDER
# ======================================================================
output_path = os.path.join(SCRIPT_DIR, 'PRESENTATION_CHAPCHAP.pptx')
prs.save(output_path)
print(f"Présentation générée : {output_path}")
print(f"  → {len(prs.slides)} slides")
print(f"  → Logo intégré : {os.path.exists(LOGO_PATH)}")
print(f"  → Notes de présentateur : oui (13 slides)")
