# Configuration Supabase — Entreprendre Ensemble pour Châteauneuf

## Étape 1 : Créer un compte Supabase

1. Aller sur **https://supabase.com** → "Start your project"
2. Se connecter avec GitHub ou email
3. Créer une organisation (ex : "Châteauneuf")

## Étape 2 : Créer le projet

1. Cliquer **"New project"**
2. **Nom** : `chateauneuf`
3. **Mot de passe BDD** : générer un mot de passe fort et le noter
4. **Région** : `West EU (Paris)` ← important pour la latence
5. Cliquer "Create new project" et attendre ~2 minutes

## Étape 3 : Récupérer les clés API

1. Aller dans **Settings** (engrenage) → **API**
2. Copier :
   - **Project URL** : `https://xxxxxxx.supabase.co`
   - **service_role key** : commence par `eyJ...` → **GARDER SECRET**

## Étape 4 : Créer les tables

1. Aller dans **SQL Editor** (barre latérale gauche)
2. Cliquer **"New query"**
3. Coller le SQL ci-dessous et cliquer **"Run"** :

```sql
-- ══════════════════════════════════════════════════════════════════
-- TABLE : subscriptions (inscriptions optin)
-- ══════════════════════════════════════════════════════════════════
CREATE TABLE subscriptions (
  id          UUID DEFAULT gen_random_uuid() PRIMARY KEY,
  prenom      TEXT NOT NULL,
  nom         TEXT NOT NULL,
  email       TEXT NOT NULL UNIQUE,
  telephone   TEXT NOT NULL,
  created_at  TIMESTAMPTZ DEFAULT now() NOT NULL
);
CREATE INDEX idx_subscriptions_created_at ON subscriptions (created_at DESC);

-- ══════════════════════════════════════════════════════════════════
-- TABLE : ideas (idées citoyennes)
-- ══════════════════════════════════════════════════════════════════
CREATE TABLE ideas (
  id          UUID DEFAULT gen_random_uuid() PRIMARY KEY,
  prenom      TEXT NOT NULL,
  nom         TEXT NOT NULL,
  email       TEXT NOT NULL,
  telephone   TEXT NOT NULL,
  sujet       TEXT NOT NULL,
  message     TEXT NOT NULL,
  is_read     BOOLEAN DEFAULT false NOT NULL,
  created_at  TIMESTAMPTZ DEFAULT now() NOT NULL
);
CREATE INDEX idx_ideas_read_date ON ideas (is_read ASC, created_at DESC);

-- ══════════════════════════════════════════════════════════════════
-- TABLE : testimonials (citations carousel)
-- ══════════════════════════════════════════════════════════════════
CREATE TABLE testimonials (
  id          SERIAL PRIMARY KEY,
  name        TEXT NOT NULL,
  profession  TEXT NOT NULL DEFAULT '',
  quote       TEXT NOT NULL,
  photo       TEXT NOT NULL DEFAULT '',
  color       TEXT NOT NULL DEFAULT '#33C1EB',
  sort_order  INT NOT NULL DEFAULT 0,
  is_active   BOOLEAN DEFAULT true NOT NULL,
  created_at  TIMESTAMPTZ DEFAULT now() NOT NULL
);
CREATE INDEX idx_testimonials_active ON testimonials (is_active, sort_order);

-- ══════════════════════════════════════════════════════════════════
-- TABLE : team_members (équipe)
-- ══════════════════════════════════════════════════════════════════
CREATE TABLE team_members (
  id          INT PRIMARY KEY,
  prenom      TEXT NOT NULL,
  nom         TEXT NOT NULL,
  role        TEXT NOT NULL DEFAULT '',
  age         INT,
  profession  TEXT NOT NULL DEFAULT '',
  photo       TEXT NOT NULL DEFAULT '',
  depuis      TEXT NOT NULL DEFAULT '',
  q1          TEXT NOT NULL DEFAULT '',
  q2          TEXT NOT NULL DEFAULT '',
  q3          TEXT NOT NULL DEFAULT '',
  sort_order  INT NOT NULL DEFAULT 0,
  is_active   BOOLEAN DEFAULT true NOT NULL,
  updated_at  TIMESTAMPTZ DEFAULT now() NOT NULL
);
CREATE INDEX idx_team_active ON team_members (is_active, sort_order, id);
```

4. Vérifier dans **Table Editor** que les 4 tables apparaissent

## Étape 5 : Activer la sécurité RLS

Toujours dans **SQL Editor**, nouvelle requête :

```sql
-- Activer RLS
ALTER TABLE subscriptions ENABLE ROW LEVEL SECURITY;
ALTER TABLE ideas ENABLE ROW LEVEL SECURITY;
ALTER TABLE testimonials ENABLE ROW LEVEL SECURITY;
ALTER TABLE team_members ENABLE ROW LEVEL SECURITY;

-- Inscriptions : le public peut s'inscrire
CREATE POLICY "anon_insert_subscriptions" ON subscriptions
  FOR INSERT TO anon WITH CHECK (true);

-- Idées : le public peut soumettre
CREATE POLICY "anon_insert_ideas" ON ideas
  FOR INSERT TO anon WITH CHECK (true);

-- Témoignages : le public peut lire les actifs
CREATE POLICY "anon_select_testimonials" ON testimonials
  FOR SELECT TO anon USING (is_active = true);

-- Équipe : le public peut lire les actifs
CREATE POLICY "anon_select_team" ON team_members
  FOR SELECT TO anon USING (is_active = true);
```

## Étape 6 : Insérer les données initiales

### 6a — Témoignages (5 citations)

```sql
INSERT INTO testimonials (name, profession, quote, photo, color, sort_order) VALUES
  ('Laure Lamour', 'Assistante audioprothésiste',
   'Je souhaite faire de Châteauneuf une commune sereine et facile à vivre, du bourg jusqu''aux petits coins les plus reculés.',
   'images/equipe/Laure Lamour.jpg', '#33C1EB', 1),
  ('Azeline Cornut', 'Éclairagiste de théâtre',
   'Une commune pensée pour les enfants est une commune plus sûre, plus solidaire, plus durable et donc plus agréable pour tous.',
   'images/equipe/Azeline Cornut.jpg', '#F79F87', 2),
  ('Melissa Artus', 'Coiffeuse, gérante A''Melisse',
   'Mon engagement repose sur l''écoute, la proximité et l''action, au service de tous les habitants de Châteauneuf.',
   'images/equipe/Melissa Artus.jpg', '#A7BF6F', 3),
  ('Annabelle Briand', 'Assistante comptable',
   'Je souhaite apporter un nouveau dynamisme à la commune — être aux côtés des habitants pour montrer que la municipalité peut être à leur écoute.',
   'images/equipe/Annabelle Briand.jpg', '#33C1EB', 4),
  ('Aurélien Bonnin', 'Électricien',
   'Je souhaite participer activement à la vie locale plutôt que rester simple observateur. Proximité, écoute, bon sens et esprit collectif.',
   'images/equipe/Aurélien Bonnin.jpg', '#F79F87', 5);
```

### 6b — Membres de l'équipe (17 colistiers)

```sql
INSERT INTO team_members (id, prenom, nom, role, age, profession, photo, depuis, q1, q2, q3, sort_order) VALUES
(1, 'Aurélien', 'Arnaud', 'Tête de liste', 36, 'Directeur conseil en agence de communication', 'images/equipe/Aurélien Arnaud.jpg', 'Castelneuvien de toujours', 'Je souhaite apporter mon expertise en communication et mon engagement sincère pour porter les projets des habitants avec clarté, transparence et ambition.', '36 ans, directeur conseil en agence de communication. Je travaille au quotidien à valoriser des projets et créer du lien entre les acteurs d''un territoire. Natif de Châteauneuf, je connais chaque association et chaque réalité de cette commune.', 'Parce que Châteauneuf mérite une équipe transparente, à l''écoute et tournée vers l''avenir. Je suis fier de porter cette liste citoyenne avec conviction.', 1),
(2, 'Fanellie', 'Egron', '', NULL, 'Infirmière', 'images/equipe/Fanellie Egron.jpg', '', '', '', '', 2),
(3, 'Jordan', 'Lahoreau', '', NULL, '', 'images/equipe/Jordan Lahoreau.jpg', '', '', '', '', 3),
(4, 'Clémence', 'Bessau', '', 25, 'Conseillère bancaire', 'images/equipe/Clémence Bessau.jpg', 'Habitante depuis 2023', 'Je souhaite apporter mon dynamisme, ma rigueur et mon sens de l''écoute afin de soutenir et contribuer à des projets concrets pour les habitants.', '25 ans, conseillère bancaire et ancienne agent immobilier. J''accompagne au quotidien des familles dans leurs projets, avec sérieux et proximité.', 'En tant que jeune maman, j''aspire à défendre une commune dynamique, attentive aux familles et tournée vers l''avenir.', 4),
(5, 'Aurélien', 'Bonnin', '', 41, 'Électricien dans le bâtiment', 'images/equipe/Aurélien Bonnin.jpg', 'Natif de la commune, Castelneuvien depuis 41 ans', 'Je souhaite m''investir concrètement. J''ai toujours aimé m''impliquer dans ce que je fais et je veux aujourd''hui mettre cette énergie au service de ma commune. Contribuer à des projets utiles, réalistes et adaptés aux besoins des Castelneuviens.', '41 ans. Après 15 ans comme technicien audiovisuel dans l''événementiel, j''ai choisi de me reconvertir et je suis maintenant électricien dans le bâtiment.', 'Parce que je souhaite participer activement à la vie locale plutôt que rester simple observateur. Cette liste correspond à mes valeurs : proximité, écoute, bon sens et esprit collectif. Je veux contribuer à des décisions réfléchies, équilibrées et tournées vers l''intérêt général.', 5),
(6, 'Azeline', 'Cornut', '', 36, 'Éclairagiste de théâtre', 'images/equipe/Azeline Cornut.jpg', 'Installée à Châteauneuf depuis 3 ans', 'Je souhaite apporter une énergie nouvelle et constructive à la commune, sans jugement ni a priori. J''aimerais contribuer à préserver et améliorer le cadre de vie, en pensant la commune à hauteur d''enfant — parce qu''une commune pensée pour les enfants est une commune plus sûre, plus solidaire, plus durable et donc plus agréable pour tous.', '36 ans, éclairagiste dans le théâtre. Ce métier de l''ombre qui met en lumière me passionne. Savant mélange entre technique et artistique, il requiert un sens du rythme, de la créativité, de l''adaptabilité et un goût pour le travail en équipe.', 'J''ai toujours eu foi en la force du collectif. La démocratie, dans ce qu''elle représente, est résolument importante à mes yeux. Cette liste citoyenne à Châteauneuf, c''est l''opportunité d''œuvrer pour l''intérêt général, avec une approche fondée sur l''écoute et la participation citoyenne.', 6),
(7, 'Yannick', 'Oiry', '', 57, 'Agent administratif exploitation (Voyages Nombalais)', 'images/equipe/Yannick Oiry.jpg', '', 'Respect du budget, transparence et ne pas augmenter la dette par habitant. Participer à la protection de notre environnement. Développer les activités pour l''ensemble des habitants. Préserver l''école. Développer le nombre d''entreprises dans la zone artisanale.', '57 ans, agent administratif exploitation aux Voyages Nombalais : gestion des heures et primes conducteurs (150 agents), suivi de la consommation carburant, mise en place des formations obligatoires, suivi des infractions, membre du CSE et référent harcèlement.', 'Je m''engage sur cette liste citoyenne pour être au service des habitants de la commune et améliorer leur quotidien.', 7),
(8, 'Annabelle', 'Briand', '', 34, 'Assistante comptable (secteur électricité & photovoltaïque)', 'images/equipe/Annabelle Briand.jpg', 'Habitante de toujours', 'Je souhaite apporter un nouveau dynamisme à la commune, être aux côtés des habitants pour leur montrer que la municipalité peut être à leur écoute. J''ai effectué 7 ans à l''OGEC de l''école, dont 6 ans au bureau et 2 ans de présidence.', '34 ans, assistante comptable dans une entreprise privée dans le domaine de l''électricité et du photovoltaïque.', 'Je m''engage pour apporter plus de soutien aux associations et à l''école, aujourd''hui en difficulté. Je n''oublie pas nos aînés, auxquels j''aimerais tisser un lien avec les plus jeunes, avec des infrastructures et activités adaptées. Je pense que la parole des Castelneuviens a été oubliée et je souhaite que cela change.', 8),
(9, 'Serge', 'Cousin', '', 69, 'Architecte DPLG (retraité)', 'images/equipe/Serge Cousin.jpg', 'Demeure à Châteauneuf depuis janvier 2021', 'Un peu de mon temps pour aider l''équipe et donc la communauté, en fonction des connaissances liées à mon vécu et à mes disponibilités pour aider la team.', '69 ans. Bac F4 (Bâtiment/Génie civil) en 1974, BTS ATEB en 1976, Architecte DPLG en 1982. Parcours riche : dessinateur en bureau d''études béton armé, architecte salarié, directeur d''un négoce matériaux, architecte libéral, puis surveillant de travaux chez des bailleurs sociaux et en cabinet d''architecture sur de grands chantiers.', 'Je m''engage pour apporter mon expérience au service de la commune et de ses habitants, avec la même disponibilité et rigueur que j''ai mises dans toute ma carrière.', 9),
(10, 'Vicky', 'Gervais', '', NULL, '', 'images/equipe/Vicky Gervais.jpg', '', '', '', '', 10),
(11, 'Olivier', 'Roux', '', NULL, '', 'images/equipe/Olivier Roux.jpg', '', '', '', '', 11),
(12, 'Melissa', 'Artus', '', 30, 'Coiffeuse, gérante du salon A''Melisse Coiffure (Bouin)', 'images/equipe/Melissa Artus.jpg', 'Habitante depuis 11 ans', 'Je souhaite m''engager pleinement pour Châteauneuf. Mon objectif est de soutenir activement les associations locales, accompagner l''école dans ses projets et contribuer à maintenir une commune dynamique et bienveillante.', '30 ans, coiffeuse depuis 15 ans et gérante de mon propre salon A''Melisse Coiffure à Bouin depuis 1 an.', 'Mon engagement repose sur l''écoute, la proximité et l''action, au service de tous les habitants de Châteauneuf. Je veux encourager les initiatives, renforcer la solidarité et veiller à ce que chacun trouve sa place dans notre commune.', 12),
(13, 'Antoine', 'Egron', '', NULL, '', 'images/equipe/Antoine Egron.jpg', '', '', '', '', 13),
(14, 'Cynthia', 'Grosseau', '', 28, 'Commerciale spécialisée carrelage & salle de bain', 'images/equipe/Cynthia Grosseau.jpg', 'Habitante depuis 2019', 'Je veux m''investir pour améliorer la sécurité, surtout pour les enfants, et favoriser un cadre de vie dynamique fondé sur l''écoute de tous.', '28 ans, travaillant dans le commerce spécialisé en carrelage et aménagement de salle de bain. Au quotidien, j''accompagne mes clients dans la réalisation de projets personnalisés, en étant à l''écoute de leurs besoins et de leurs attentes.', 'Je rejoins cette liste pour sa convivialité et la diversité des idées. Chacun possède un savoir-faire à partager.', 14),
(15, 'Thomas', 'Barraud', '', NULL, '', 'images/equipe/Thomas barraud.jpg', '', '', '', '', 15),
(16, 'Laure', 'Lamour', '', 38, 'Assistante audioprothésiste, en reconversion comme carreleuse', 'images/equipe/Laure Lamour.jpg', 'Sur la commune depuis 2013', 'Grâce à mon engagement associatif actuel dans la commune, je connais déjà quelques acteurs locaux et la municipalité. Mes métiers dans le paramédical m''ont apporté une capacité d''organisation et des connaissances en démarches administratives. Habitant hors du bourg, je suis à même de comprendre les problématiques liées à cet éloignement.', '38 ans, ancienne opticienne pendant 10 ans, puis assistante audioprothésiste depuis 8 ans. Actuellement en reconversion professionnelle comme carreleuse.', 'Arrivée sur la commune en 2013, nous avons réussi une belle intégration. Je souhaite que chaque citoyen prenne sa place et devienne acteur sur la commune. J''aimerais dynamiser notre territoire pour attirer de nouveaux habitants, pérenniser l''école, le périscolaire, les commerces et les emplois communaux. Je veux faire de Châteauneuf une commune sereine et facile à vivre, du bourg jusqu''aux petits coins les plus reculés.', 16),
(17, 'Jordan', 'Belanger', '', NULL, '', 'images/equipe/Jordan Belanger.jpg', '', '', '', '', 17);
```

## Étape 7 : Configurer Vercel

1. Aller dans **Vercel** → ton projet → **Settings** → **Environment Variables**
2. Ajouter :

| Variable | Valeur |
|----------|--------|
| `SUPABASE_URL` | `https://xxxxxxx.supabase.co` (Project URL de l'étape 3) |
| `SUPABASE_SERVICE_KEY` | La clé `service_role` de l'étape 3 |

3. S'assurer que ces variables sont définies pour **Production + Preview + Development**
4. Cliquer **Save**

## Étape 8 : Redéployer

Après avoir ajouté les variables :
1. Aller dans **Deployments** dans Vercel
2. Cliquer sur les 3 points du dernier déploiement → **Redeploy**
3. Ou simplement pusher un nouveau commit

## Étape 9 : Vérifier

1. **Site public** : le carousel de témoignages et la grille d'équipe se chargent normalement
2. **Formulaire inscription** : soumettre une inscription → vérifier dans Supabase Table Editor → table `subscriptions`
3. **Formulaire idée** : soumettre une idée → vérifier dans `ideas` + email reçu
4. **Admin** : se connecter → les inscriptions et idées s'affichent depuis Supabase
5. **Export** : tester les boutons CSV et Excel dans l'admin

---

## Résumé de l'architecture

```
┌──────────────┐     ┌──────────────────┐     ┌───────────┐
│  Site public │────▶│  Vercel API      │────▶│ Supabase  │
│  index.html  │     │  /api/*.js       │     │ PostgreSQL│
└──────────────┘     └──────────────────┘     └───────────┘
                            │
                     ┌──────▼──────┐
                     │  Brevo SMTP │
                     │  (emails)   │
                     └─────────────┘
```

- **Lecture publique** : `/api/testimonials` + `/api/team` (avec cache 5min)
- **Écriture publique** : `/api/subscribe` + `/api/submit-idea` → Supabase + emails
- **Admin** : `/api/admin-*` → authentifié par token HMAC
- **Export** : `/api/admin-export?type=subscriptions&format=excel`
