# Rapport de Faisabilité - Analytics Dashboard Avancé

**Date:** 2025-01-XX  
**Contexte:** Analyse des capacités du système actuel pour calculer les métriques demandées pour un dashboard analytics premium.

---

## Résumé Exécutif

Le système actuel dispose de **données solides pour l'engagement utilisateur et la performance du contenu**, mais **manque complètement d'infrastructure de monétisation**. Environ **60% des métriques demandées sont calculables immédiatement**, **25% nécessitent des dérivations/approximations**, et **15% nécessitent de nouveaux événements de tracking**.

---

## 1. Business Health & Monetization ("The Bottom Line")

### ❌ **Impossible / Nécessite Nouveau Tracking**

#### MRR (Monthly Recurring Revenue)
- **Statut:** ❌ Impossible
- **Raison:** Aucune table de paiements/abonnements dans le système
- **Action requise:** 
  - Créer table `subscriptions` (user_id, plan_id, status, start_date, end_date, amount)
  - Intégrer avec système de paiement (Stripe, PayPal, etc.)

#### ARR (Annual Run Rate)
- **Statut:** ❌ Impossible (dépend de MRR)
- **Action requise:** Même que MRR

#### ARPU (Average Revenue Per User)
- **Statut:** ❌ Impossible
- **Action requise:** Même que MRR

#### LTV (Life Time Value)
- **Statut:** ❌ Impossible
- **Action requise:** 
  - Table `subscriptions` + historique des paiements
  - Calculer: Somme de tous les paiements d'un utilisateur depuis son inscription

#### Conversion Rate (Free → Paid)
- **Statut:** ❌ Impossible
- **Action requise:** 
  - Ajouter champ `subscription_status` dans `users` ou table dédiée
  - Tracker les événements de conversion

#### Churn Rate (Monthly)
- **Statut:** ❌ Impossible
- **Action requise:** 
  - Table `subscriptions` avec statut (active, cancelled, expired)
  - Tracker les dates d'annulation

#### Revenue Churn
- **Statut:** ❌ Impossible (dépend de Revenue tracking)
- **Action requise:** Même que MRR

#### Refund Rate
- **Statut:** ❌ Impossible
- **Action requise:** 
  - Table `transactions` avec champ `refunded_at` ou `refund_status`
  - Intégration avec système de paiement pour tracker les remboursements

---

## 2. Retention & Cohorts ("The Sticky Factor")

### ✅ **Immédiatement Disponible**

#### User Retention Curves (D1 / D7 / D30)
- **Statut:** ✅ **Calculable avec approximation**
- **Source de données:** 
  - `user_exercises.created_at` (première activité)
  - `user_exercises.updated_at` (dernière activité)
  - `users.created_at` (date d'inscription)
- **Méthode de calcul:**
  ```sql
  -- D1 Retention: Utilisateurs actifs 1 jour après inscription
  SELECT COUNT(DISTINCT u.id) 
  FROM users u
  INNER JOIN user_exercises ue ON ue.user_id = u.id
  WHERE u.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    AND ue.created_at BETWEEN u.created_at AND DATE_ADD(u.created_at, INTERVAL 1 DAY)
  ```
- **Note:** Approximation basée sur l'activité d'exercice, pas sur les sessions réelles

#### Resurrection Rate
- **Statut:** ✅ **Calculable**
- **Source de données:** 
  - `user_exercises.updated_at` (dernière activité)
  - Comparer avec activité récente
- **Méthode:** Utilisateurs avec `updated_at` > 30 jours avant aujourd'hui ET activité dans les 7 derniers jours

### ⚠️ **Calculable avec Limitations**

#### Stickiness Ratio (DAU/MAU)
- **Statut:** ⚠️ **Calculable avec approximation**
- **Source de données:** `user_exercises.updated_at` (approximation de l'activité)
- **Limitation:** Pas de tracking réel des sessions, seulement activité sur exercices
- **Méthode:** 
  - DAU: Utilisateurs uniques avec `user_exercises.updated_at` dans les 24h
  - MAU: Utilisateurs uniques avec `user_exercises.updated_at` dans les 30 jours

#### Churn Risk Segment (Inactifs > 14 jours)
- **Statut:** ✅ **Calculable**
- **Source de données:** `user_exercises.updated_at`
- **Méthode:** Utilisateurs avec dernière activité > 14 jours ET < 30 jours

---

## 3. Deep User Engagement ("Product Usage")

### ✅ **Immédiatement Disponible**

#### DAU (Daily Active Users)
- **Statut:** ✅ **Calculable avec approximation**
- **Source de données:** `user_exercises.updated_at`
- **Méthode:** `SELECT COUNT(DISTINCT user_id) FROM user_exercises WHERE updated_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)`
- **Note:** Basé sur activité exercice, pas sessions réelles

#### WAU (Weekly Active Users)
- **Statut:** ✅ **Calculable**
- **Source de données:** `user_exercises.updated_at`
- **Méthode:** Même logique que DAU avec intervalle de 7 jours

#### MAU (Monthly Active Users)
- **Statut:** ✅ **Calculable**
- **Source de données:** `user_exercises.updated_at`
- **Méthode:** Même logique que DAU avec intervalle de 30 jours

#### Average Session Duration
- **Statut:** ⚠️ **Calculable avec approximation**
- **Source de données:** `user_exercises.watch_time` (temps cumulé par jour)
- **Méthode:** 
  - Grouper `watch_time` par `user_id` et `DATE(created_at)`
  - Calculer moyenne par "session" (jour)
- **Limitation:** Pas de tracking réel de session (ouverture/fermeture app)

#### Sessions Per User
- **Statut:** ⚠️ **Calculable avec approximation**
- **Source de données:** `user_exercises.created_at` (nombre de jours avec activité)
- **Méthode:** `COUNT(DISTINCT DATE(created_at))` par utilisateur sur période
- **Limitation:** Approximatif, basé sur jours avec activité, pas sessions réelles

### ❌ **Nécessite Nouveau Tracking**

#### Time of Day Activity Heatmap
- **Statut:** ❌ **Nécessite nouveau tracking**
- **Raison:** `user_exercises.created_at` et `updated_at` ne capturent pas l'heure précise d'activité
- **Action requise:**
  - Table `user_sessions` (user_id, started_at, ended_at, timezone)
  - Ou enrichir `user_exercises` avec `activity_hour` (0-23)
  - Tracker les événements d'ouverture/fermeture d'app

---

## 4. Content Performance ("Content Strategy")

### ✅ **Immédiatement Disponible**

#### Exercise Completion Rate
- **Statut:** ✅ **Immédiatement disponible**
- **Source de données:** 
  - `user_exercises.completed_at` (IS NOT NULL = complété)
  - `user_exercises.created_at` (début)
- **Méthode:** 
  ```sql
  SELECT 
    exercise_id,
    COUNT(*) as started,
    COUNT(completed_at) as completed,
    (COUNT(completed_at) / COUNT(*)) * 100 as completion_rate
  FROM user_exercises
  GROUP BY exercise_id
  ```

#### Most Popular Exercises
- **Statut:** ✅ **Immédiatement disponible**
- **Source de données:** `user_exercises.exercise_id`
- **Méthode:** `SELECT exercise_id, COUNT(DISTINCT user_id) as unique_starts FROM user_exercises GROUP BY exercise_id ORDER BY unique_starts DESC LIMIT 10`

#### Highest Drop-off Content
- **Statut:** ✅ **Calculable**
- **Source de données:** 
  - `user_exercises.watch_time` vs `exercises.duration`
  - `user_exercises.completed_at` (NULL = abandon)
- **Méthode:** 
  - Exercices avec ratio `watch_time / duration` < 0.8 ET `completed_at IS NULL`
  - Trier par nombre d'abandons

### ❌ **Nécessite Nouveau Tracking**

#### Viral Coefficient
- **Statut:** ❌ **Nécessite nouveau tracking**
- **Raison:** Aucun système de références/parrainage
- **Action requise:**
  - Table `referrals` (referrer_user_id, referred_user_id, created_at)
  - Champ `referred_by` dans `users`
  - Calcul: (Nombre de nouveaux utilisateurs référés) / (Nombre d'utilisateurs référents)

---

## 5. User Segmentation ("Ambassadors vs Ghosts")

### ✅ **Immédiatement Disponible**

#### Power Users (Top 1%)
- **Statut:** ✅ **Immédiatement disponible**
- **Source de données:** 
  - `user_profiles.total_xp` (par XP)
  - `user_profiles.total_training_time` (par temps)
- **Méthode:** 
  ```sql
  SELECT user_id, total_xp 
  FROM user_profiles 
  ORDER BY total_xp DESC 
  LIMIT (SELECT COUNT(*) * 0.01 FROM user_profiles)
  ```

### ⚠️ **Calculable avec Limitations**

#### Device Breakdown (iOS vs Android)
- **Statut:** ⚠️ **Partiellement disponible**
- **Source de données:** 
  - `personal_access_tokens.name` (contient `device_name` lors de login)
  - `sessions.user_agent` (peut être parsé pour OS)
- **Limitation:** 
  - `device_name` est un string libre, pas structuré
  - `user_agent` nécessite parsing
- **Action recommandée:** 
  - Ajouter champs `platform` (ios/android) et `device_model` dans table dédiée
  - Ou enrichir `personal_access_tokens` avec ces champs

### ❌ **Nécessite Nouveau Tracking**

#### Geography (Country/City)
- **Statut:** ❌ **Nécessite nouveau tracking**
- **Raison:** Aucune donnée de géolocalisation
- **Action requise:**
  - Table `user_locations` (user_id, country, city, ip_address, detected_at)
  - Ou champ `country_code` dans `users`
  - Utiliser service de géolocalisation IP (MaxMind, GeoIP2)

#### App Version Adoption
- **Statut:** ❌ **Nécessite nouveau tracking**
- **Raison:** Aucun tracking de version d'app utilisée
- **Action requise:**
  - Table `app_sessions` (user_id, app_version, platform, created_at)
  - Ou enrichir `personal_access_tokens` avec `app_version`
  - Endpoint pour envoyer version lors de login/activité

---

## Recommandations Prioritaires

### 🔴 **Priorité Haute (Pour Business Health)**
1. **Créer infrastructure de monétisation:**
   - Table `subscriptions` (user_id, plan_id, status, amount, start_date, end_date)
   - Table `transactions` (user_id, subscription_id, amount, status, refunded_at)
   - Permettra: MRR, ARR, ARPU, LTV, Churn Rate, Conversion Rate, Refund Rate

### 🟡 **Priorité Moyenne (Pour Engagement)**
2. **Améliorer tracking de sessions:**
   - Table `user_sessions` (user_id, started_at, ended_at, duration_seconds, timezone)
   - Permettra: Session Duration précise, Time of Day Heatmap, Sessions Per User

3. **Enrichir données utilisateur:**
   - Champs `platform` (ios/android), `app_version`, `country_code` dans table dédiée ou `users`
   - Permettra: Device Breakdown précis, App Version Adoption, Geography

### 🟢 **Priorité Basse (Nice to Have)**
4. **Système de références:**
   - Table `referrals` pour tracking viral
   - Permettra: Viral Coefficient

---

## Métriques Disponibles Immédiatement (Sans Nouveau Tracking)

✅ **Total: 12 métriques**
- User Retention Curves (D1/D7/D30) - avec approximation
- Resurrection Rate
- Stickiness Ratio (DAU/MAU) - avec approximation
- Churn Risk Segment
- DAU/WAU/MAU - avec approximation
- Average Session Duration - avec approximation
- Sessions Per User - avec approximation
- Exercise Completion Rate
- Most Popular Exercises
- Highest Drop-off Content
- Power Users (Top 1%)

---

## Métriques Nécessitant Nouveau Tracking

❌ **Total: 8 métriques**
- MRR, ARR, ARPU, LTV
- Conversion Rate, Churn Rate, Revenue Churn, Refund Rate
- Time of Day Activity Heatmap
- Viral Coefficient
- Geography
- App Version Adoption
- Device Breakdown (partiellement)

---

## Conclusion

Le système actuel est **excellent pour l'analyse d'engagement et de contenu** mais **manque complètement d'infrastructure de monétisation**. 

**Recommandation:** Commencer par l'infrastructure de monétisation (Priorité Haute) car elle bloque 8 métriques critiques pour un dashboard business. Ensuite, améliorer le tracking de sessions pour des métriques plus précises.

**Estimation effort:**
- Infrastructure monétisation: 2-3 semaines
- Tracking sessions amélioré: 1 semaine
- Enrichissement données utilisateur: 3-5 jours
- Système références: 1 semaine


