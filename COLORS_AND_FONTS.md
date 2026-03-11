# 🎨 Luxury Hotel - Palette Colori & Typography

## 🌾 Nuova Palette Colori (Beige/Panna/Marrone)

### Colori Primari

| Colore | Codice Hex | Utilizzo |
|--------|-----------|----------|
| **Marrone Elegante** | `#8B6F47` | Primary - Bottoni, header, link principali |
| **Beige Marrone** | `#C9A876` | Secondary - Accenti, gradient, hover states |
| **Beige Chiaro** | `#D4C4B8` | Accent - Borders, highlights |
| **Marrone Profondo** | `#4A3728` | Dark - Titoli, testo principale |
| **Panna** | `#F9F5F0` | Light - Background, card backgrounds |

### Shadow System
```css
--shadow-sm: 0 2px 8px rgba(74, 55, 40, 0.08);
--shadow-md: 0 8px 24px rgba(74, 55, 40, 0.12);
--shadow-lg: 0 16px 48px rgba(74, 55, 40, 0.15);
--shadow-xl: 0 24px 56px rgba(139, 111, 71, 0.2);
```

## 🔤 Google Fonts Eleganti

### Font Utilizzati

1. **Playfair Display** (Titoli grandi)
   - Font-Family: `'Playfair Display', serif`
   - Utilizzato per: h1, h2, h3, logo, prezzi
   - Stile: Serif elegante e sofisticato
   - Pesi: 700 (Bold), 900 (Black)

2. **Lora** (Corpo testo)
   - Font-Family: `'Lora', serif`
   - Utilizzato per: body, p, descrizioni
   - Stile: Serif leggibile e elegante
   - Pesi: 400 (Regular), 500 (Medium), 600 (Semibold)

3. **Poppins** (UI Elements)
   - Font-Family: `'Poppins', sans-serif`
   - Utilizzato per: bottoni, label, link di navigazione
   - Stile: Sans-serif pulito e moderno
   - Pesi: 400 (Regular), 500 (Medium), 600 (Semibold), 700 (Bold)

### Import Google Fonts

```html
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=Lora:wght@400;500;600&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
```

## 🎨 Esempi di Utilizzo

### Titoli Principali (Playfair Display)
```css
h1, h2, h3, h4, h5, h6 {
    font-family: 'Playfair Display', serif;
    font-weight: 700;
    color: var(--dark-color);  /* #4A3728 */
}
```

### Corpo Testo (Lora)
```css
p {
    font-family: 'Lora', serif;
    color: var(--text-color);  /* #5a5a5a */
    line-height: 1.8;
}
```

### UI Elements (Poppins)
```css
.btn, label, .nav a {
    font-family: 'Poppins', sans-serif;
    font-weight: 600;
}
```

## 🔄 Gradient Colori

### Primary Gradient
```css
background: linear-gradient(135deg, #8B6F47 0%, #9b7d52 100%);
```

### Secondary Gradient
```css
background: linear-gradient(135deg, #C9A876 0%, #D4C4B8 100%);
```

## 🧩 Componenti Aggiornati

### Navbar
- Background: Gradient marrone morbido
- Border-radius: 20px (angoli arrotondati)
- Font: Poppins (nav links)
- Shadow: var(--shadow-md)

### Hero Section
- Overlay: Gradient beige/marrone trasparente
- Title: Playfair Display 3.5rem, weight 900
- Subtitle: Lora, weight 400

### Button Primary
- Background: Gradient marrone
- Hover: Traslazione verso l'alto con shadow più grande
- Border-radius: 20px

### Button Secondary
- Background: Bianco
- Border: 2px solid #8B6F47
- Color text: #8B6F47
- Hover: Background #8B6F47, color white

### Card
- Background: Bianco
- Border-radius: 20px
- Shadow: var(--shadow-md) → var(--shadow-xl) on hover
- Accent line: Top 4px solid #8B6F47

## 📱 Responsive

Tutti gli elementi mantengono la palette colori e i font Google su tutti i breakpoint:
- Extra Small (320px-374px)
- Small Mobile (375px-599px)
- Tablet (600px-1023px)
- Desktop (1024px+)

## 🎯 Caratteristiche Design

✨ **Eleganza naturale**: La palette beige/marrone evoca lusso e sofisticazione
✨ **Tipografia armoniosa**: I tre font Google creano una tipografia elegante e coerente
✨ **Morbidezza**: Border-radius 20px su navbar e card per un look più morbido
✨ **Shadow sofisticate**: Ombreggiature dolci che mantengono l'eleganza
✨ **Contrasto leggibile**: Testo scuro su sfondo chiaro per massima leggibilità

---

**Versione**: 2.0
**Ultimo Aggiornamento**: 2026-03-11
⭐ **Status**: Design completamente rinnovato e elegantissimo!
