/* CLÉOPÂTRE — Répertoire marques (LEGACY — DB est source de vérité)
 * Source primaire: SQLite table brands + /api/brands/list.php & /api/admin/brands.php
 * Ce fichier reste pour fallback et compat avec js/global.js qui hydrate depuis l'API.
 * Toute nouvelle marque doit être créée via l'admin (qui écrit en DB).
 */
window.CLEO_BRANDS = [
  {
    slug: "la-roche-posay", name: "La Roche-Posay", country: "France", est: "1905",
    letter: "L", featured: true, tint: "#DCE3EA",
    tagline: "La dermatologie prescriptive",
    story: ["Née autour d’une source thermale de la Vienne, La Roche-Posay a fait du sélenium thermal un principe actif à part entière. Ses formules sont conçues avec des dermatologues, testées sur les peaux les plus réactives et validées en usage pédiatrique.",
      "Chez Cléopâtre, c’est la maison que nous recommandons lorsque la peau ne pardonne pas : rosacée, eczéma, post-acte. Une pharmacie dans la pharmacie."],
    signature: "Anthelios · Cicaplast · Effaclar",
    values: ["Testé sous contrôle dermatologique", "Formules minimalistes", "Peaux réactives"]
  },
  {
    slug: "avene", name: "Avène", country: "France", est: "1990",
    letter: "A", featured: true, tint: "#E3E8DE",
    tagline: "L’eau qui apaise, depuis 1743",
    story: ["Au cœur des Cévennes, l’eau thermale d’Avène parcourt quarante ans de roche avant de jaillir. Sa richesse en silice et son pH quasi neutre en font un soin à part entière, pas une simple brume.",
      "La marque Pierre Fabre accompagne depuis trente ans les peaux atopiques et intolérantes avec une obsession : la tolérance maximale au prix minimal d’ingrédients."],
    signature: "Eau Thermale · Tolérance Control · Cold Cream",
    values: ["Hydrothérapie historique", "Minimalisme formulé", "Fabrication France"]
  },
  {
    slug: "bioderma", name: "Bioderma", country: "France", est: "1977",
    letter: "B", featured: true, tint: "#DFE7EC",
    tagline: "La biologie au service de la peau",
    story: ["Laboratoire indépendant devenu légendaire pour ses eaux micellaires, Bioderma raisonne en biomimétisme : imiter les mécanismes naturels de la peau plutôt que les saturer.",
      "Sensibio, Sébium, Atoderm — chaque ligne répond à une physiologie précise. C’est cette précision que nos clientes reconnaissent dès le premier flacon."],
    signature: "Sensibio H2O · Sébium · Atoderm",
    values: ["Biomimétisme", "Eau micellaire d’origine", "Tolérance prouvée"]
  },
  {
    slug: "nuxe", name: "Nuxe", country: "France", est: "1957",
    letter: "N", featured: true, tint: "#F0E4CF",
    tagline: "La nature, sublimée par la science",
    story: ["Huile Prodigieuse : sept huiles végétales, un parfum de sable chaud devenu culte mondial. Nuxe incarne une cosmétique naturelle française qui refuse l’austérité.",
      "Des textures gourmandes, des gestes sensoriels, une efficacité documentée : le plaisir n’y est jamais un vernis, c’est la méthode."],
    signature: "Huile Prodigieuse · Rêve de Miel · Hair Prodigieux",
    values: ["Actifs botaniques", "Textures sensorielles", "Made in France"]
  },
  {
    slug: "caudalie", name: "Caudalie", country: "France", est: "1995",
    letter: "C", featured: true, tint: "#E8DED6",
    tagline: "Le vigneron de la beauté",
    story: ["Des pépins de raisin aux polyphénols brevetés par Harvard, Caudalie a fait de la vigne son laboratoire. Vinoperfect reste la référence anti-taches à base naturelle.",
      "Une maison engagée : zéro parabène, zéro phénoxyéthanol, et un arbre planté pour chaque commande. Le luxe y a un accent écologique assumé."],
    signature: "Vinoperfect · Eau de Beauté · Soleil des Vignes",
    values: ["Polyphénols de raisin", "Clean beauty pionnier", "Écolabels certifiés"]
  },
  {
    slug: "vichy", name: "Vichy", country: "France", est: "1931",
    letter: "V", featured: false, tint: "#D9E2E4",
    tagline: "L’eau volcanique, 15 minéraux",
    story: ["Issue des volcans d’Auvergne, l’eau de Vichy renforce la barrière cutanée et rend la peau plus résistante au stress oxydatif. Minéral 89 en condense la promesse en un seul geste.",
      "Un laboratoire complet visage-cheveux, où chaque formule commence par cette eau régénératrice."],
    signature: "Minéral 89 · Dercos · Liftactiv",
    values: ["Eau volcanique", "Barrière cutanée", "Santé & beauté"]
  },
  {
    slug: "mustela", name: "Mustela", country: "France", est: "1950",
    letter: "M", featured: true, tint: "#F2E8DC",
    tagline: "Spécialiste de la peau immature",
    story: ["Soixante-dix ans consacrés à une seule peau : celle des bébés. Mustela travaille avec des sages-femmes et pédiatres européens pour des formules ultra-sûres, traçables, souvent bio.",
      "Stelatopia accompagne les nourrissons atteints d’eczéma atopique ; Hydra-Bébé reste notre best-seller de maternité."],
    signature: "Hydra-Bébé · Stelatopia · Cicastela",
    values: ["Contrôle pédiatrique", "Actifs d’origine naturelle", "Engagement durable"]
  },
  {
    slug: "filorga", name: "Filorga", country: "France", est: "1978",
    letter: "F", featured: true, tint: "#E9E2EE",
    tagline: "La médecine esthétique en flacon",
    story: ["Laboratoire parisien fondé par un médecin esthétique, Filorga transpose en soins quotidiens les actifs de ses injections : NCEF, acide hyaluronique fraîchement encapsulé.",
      "Time-Filler agit comme un lifting topique ; UV Cellular Protect protège en corrigeant. L’exigence clinique, sans aiguille."],
    signature: "Time-Filler · Oxygen-Glow · UV Cellular",
    values: ["NCEF breveté", "Anti-âge injectable-like", "Conçu à Paris"]
  },
  {
    slug: "svr", name: "SVR", country: "France", est: "1962",
    letter: "S", featured: false, tint: "#E4EAE3",
    tagline: "La science vertigineuse du détail",
    story: ["Deux pharmaciens, une obsession : la concentration optimale. SVR pousse ses actifs — acide hyaluronique, niacinamide — jusqu’au seuil d’efficacité maximal.",
      "Sun Secure réinvente le solaire en mousse invisible ; Topialyse répare les peaux sèches les plus inconfortables. Des produits d’initiés, devenus cultes."],
    signature: "Sun Secure · Topialyse · Sebiaclear",
    values: ["Dosages maximaux", "Innovation solaire", "Fabriqué en France"]
  },
  {
    slug: "uriage", name: "Uriage", country: "France", est: "1992",
    letter: "U", featured: false, tint: "#DEE6E9",
    tagline: "L’eau isotonique des Alpes",
    story: ["À 1 600 mètres d’altitude, l’eau thermale d’Uriage est isotonique : elle respecte instantanément l’équilibre cellulaire. Un confort unique pour les peaux fragilisées.",
      "Sa gamme Bariésun illustre sa philosophie : protection maximale, textures plaisir, tolérance absolue."],
    signature: "Eau Thermale · Bariésun · Xémose",
    values: ["Isotonique", "Haute montagne", "Peaux fragilisées"]
  },
  {
    slug: "ducray", name: "Ducray", country: "France", est: "1930",
    letter: "D", featured: false, tint: "#E5E5DB",
    tagline: "Le cuir chevelu traité en dermatologie",
    story: ["Groupe Pierre Fabre, Ducray aborde le cheveu comme un organe : anaphase contre la chute, Kelual contre les dermatites séborrhéiques, Nutriceric contre les pellicules.",
      "Nos conseillères composent ici de véritables protocoles capillaires, shampoing après sérum."],
    signature: "Anaphase+ · Kelual DS · Nutriceric",
    values: ["Diagnostic capillaire", "Traitement ciblé", "Expertise Pierre Fabre"]
  },
  {
    slug: "a-derma", name: "A-Derma", country: "France", est: "1990",
    letter: "A", featured: false, tint: "#EAEAD9",
    tagline: "Le rhealba, fil d’avoine breveté",
    story: ["Une seule plante cultivée en agriculture biologique : l’avoine Rhealba. De ses jeunes pousses, A-Derma extrait des saponines apaisantes d’une douceur rare.",
      "Pour les peaux qui rougissent de tout, c’est souvent notre premier réflexe comptoir."],
    signature: "Rhéacalm · Exomega · Dermalibour",
    values: ["Avoine bio", "Apaisant naturel", "Bébé & adulte"]
  },
  {
    slug: "eucerin", name: "Eucerin", country: "Allemagne", est: "1900",
    letter: "E", featured: false, tint: "#E2E4E8",
    tagline: "La précision allemande",
    story: ["Plus d’un siècle de recherche dermatologique allemande. Eucerin excelle là où la rigueur compte : photodermatologie avec Sun Oil Control, hyperpigmentation avec Anti-Pigment.",
      "Des études cliniques publiées, des allégations mesurées. L’anti-promesse marketing."],
    signature: "Sun Oil Control · Anti-Pigment · Aquaporin",
    values: ["Recherche clinique", "Photoprotection experte", "Précision dosée"]
  },
  {
    slug: "roge-cavailles", name: "Rogé Cavaillès", country: "France", est: "1937",
    letter: "R", featured: false, tint: "#EFE3DA",
    tagline: "L’hygiène douce depuis toujours",
    story: ["Pionnier du soin lavant doux en France, Rogé Cavaillès a imposé l’idée qu’un produit d’hygiène pouvait respecter la flore cutanée.",
      "Ses formats nomades — sticks, mousses — accompagnent les journées les plus longues avec une fraîcheur discrète."],
    signature: "Stick Fraîcheur · Soin Lavant · Intime",
    values: ["Hygiène respectueuse", "Formats voyage", "Flore cutanée"]
  },
  {
    slug: "puressentiel", name: "Puressentiel", country: "France", est: "2005",
    letter: "P", featured: false, tint: "#E6EBDD",
    tagline: "L’aromathérapie scientifique",
    story: ["Chémotypées, 100% pures et naturelles : les huiles essentielles Puressentiel sont contrôlées lot par lot et documentées par un comité scientifique.",
      "Diffusions, massages, rituels du soir : la botanique comme hygiène de vie."],
    signature: "Rituels · Diffusion · Huiles chémotypées",
    values: ["HE chémotypées", "100% naturel", "Comité scientifique"]
  },
  {
    slug: "forte-pharma", name: "Forté Pharma", country: "France", est: "1992",
    letter: "F", featured: false, tint: "#EDE6D8",
    tagline: "Les compléments qui tiennent parole",
    story: ["TurboDraine, Expert Minceur : Forté Pharma construit des compléments à dosage pharmaceutique avec des extraits titrés et des résultats mesurés en études.",
      "Notre sélection santé privilégie cette transparence : ce qui est écrit sur l’étui se trouve dans le flacon."],
    signature: "TurboDraine · Slim 24 · Équilibre",
    values: ["Dosage pharmaceutique", "Extraits titrés", "Études d’efficacité"]
  }
];
