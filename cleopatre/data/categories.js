/* CLÉOPÂTRE — Univers éditoriaux (LEGACY — DB est source de vérité)
 * Source primaire: SQLite table categories + /api/categories/list.php
 * Gardé pour fallback et pour sous-catégories non encore migrées (subcategories table vide).
 */
window.CLEO_CATEGORIES = [
  {
    slug: "visage",
    name: "Visage",
    eyebrow: "Univers 01",
    tagline: "Le protocole du teint juste",
    description: "Nettoyants millimétrés, sérums d’actifs, solaires invisibles : une sélection dermatologique pensée comme une consultation.",
    intro: "Du nettoyant au sérum, chaque geste compte. Nos pharmaciens composent des routines courtes, ciblées, tolérées par les peaux les plus exigeantes.",
    accent: "#8A9481", surface: "#E9E4D8", form: "dropper",
    keywords: ["crème", "sérum", "nettoyant", "solaire", "anti-âge"],
    image: "assets/images/univers/visage.webp"
  },
  {
    slug: "corps",
    name: "Corps",
    eyebrow: "Univers 02",
    tagline: "Le luxe d’une peau entretenue",
    description: "Huiles lavantes, baumes nourrissants, soins des mains et gommages : la discipline douce du quotidien.",
    intro: "L’hydratation du corps n’est pas un supplément d’âme. Nous choisissons des textures qui se fondent instantanément et des parfums qui restent discrets.",
    accent: "#B39B72", surface: "#EFE7D9", form: "pump",
    keywords: ["lait", "huile", "baume", "douche", "mains"],
    image: "assets/images/univers/corps.webp"
  },
  {
    slug: "cheveux",
    name: "Cheveux",
    eyebrow: "Univers 03",
    tagline: "Botanique de la fibre",
    description: "Shampoings traitants, sérums fortifiants, masques riches. La chevelure traitée comme une matière noble.",
    intro: "Cuirs chevelus sensibles, chutes saisonnières, boucles assoiffées : le diagnostic d’abord, le produit ensuite. Jamais l’inverse.",
    accent: "#5C6B54", surface: "#E4E6DC", form: "bottle",
    keywords: ["shampoing", "sérum", "masque", "anti-chute", "boucles"],
    image: "assets/images/univers/cheveux.webp"
  },
  {
    slug: "bebe",
    name: "Bébé",
    eyebrow: "Univers 04",
    tagline: "La douceur en héritage",
    description: "Soins lavants, laits protecteurs et baumes réparateurs formulés pour la peau immature des tout-petits.",
    intro: "Une peau de bébé ne se traite pas comme une peau d’adulte. Formules minimalistes, testées sous contrôle pédiatrique, parfums tenus au strict nécessaire.",
    accent: "#C2A683", surface: "#F1EBE0", form: "pump",
    keywords: ["lait", "baume", "lavant", "change", "maternité"],
    image: "assets/images/univers/bebe.webp"
  },
  {
    slug: "sante",
    name: "Santé",
    eyebrow: "Univers 05",
    tagline: "L’essentiel, rigoureusement",
    description: "Compléments alimentaires, vitamines et matériel médical choisis avec la rigueur du comptoir.",
    intro: "Un complément ne remplace rien : il soutient. Nous ne référençons que des laboratoires documentés, des dosages lisibles, des promesses mesurables.",
    accent: "#46543F", surface: "#E7E7DE", form: "box",
    keywords: ["complément", "vitamines", "immunité", "minceur"],
    image: "assets/images/univers/sante.webp"
  },
  {
    slug: "bien-etre",
    name: "Bien-être",
    eyebrow: "Univers 06",
    tagline: "Rituels, huiles & silence",
    description: "Huiles végétales, essences botaniques, coffrets sensoriels. Le contrepoint calme des jours pressés.",
    intro: "Un rituel du soir vaut dix resolutions. Huiles précieuses, eaux florales et coffrets à offrir — ou à s’offrir, c’est souvent mieux.",
    accent: "#A98E63", surface: "#EDE4D3", form: "spray",
    keywords: ["huile", "essentielle", "coffret", "massage", "bio"],
    image: "assets/images/univers/bien-etre.webp"
  }
];
