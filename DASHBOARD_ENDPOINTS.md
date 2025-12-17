# Documentation des Endpoints pour le Dashboard Web

## 📋 Endpoints Disponibles Actuellement

### 🔐 Authentification
| Méthode | Endpoint | Description | Auth Requise |
|---------|----------|-------------|--------------|
| `POST` | `/api/register` | Inscription d'un nouvel utilisateur | ❌ |
| `POST` | `/api/login` | Connexion d'un utilisateur | ❌ |
| `POST` | `/api/logout` | Déconnexion | ✅ |
| `GET` | `/api/user` | Récupère l'utilisateur authentifié | ✅ |
| `DELETE` | `/api/user` | Supprime les données utilisateur | ✅ |

### 👤 Utilisateurs
| Méthode | Endpoint | Description | Auth Requise |
|---------|----------|-------------|--------------|
| `GET` | `/api/user/{userId}` | Récupère un utilisateur par ID | ❌ |

### 📊 Statistiques & Données Utilisateur
| Méthode | Endpoint | Description | Auth Requise | Paramètres |
|---------|----------|-------------|--------------|------------|
| `GET` | `/api/home` | Données de la page d'accueil (XP total, temps d'entraînement, dernières vidéos complétées) | ✅ | - |
| `GET` | `/api/user/stats` | Statistiques détaillées de l'utilisateur | ✅ | `start_date`, `end_date`, `period` (day/week/month) |
| `GET` | `/api/rankings` | Classements des utilisateurs | ✅ | `type` (day/week/month) |

### 🏋️ Exercices
| Méthode | Endpoint | Description | Auth Requise |
|---------|----------|-------------|--------------|
| `GET` | `/api/exercises` | Liste de tous les exercices | ❌ |
| `GET` | `/api/exercises/user/{userId}` | Exercices d'un utilisateur spécifique | ❌ |
| `GET` | `/api/exercises/{exerciseId}/user/{userId}` | Détails d'un exercice pour un utilisateur | ❌ |
| `GET` | `/api/exercises/level/{levelId}/user/{userId}` | Exercices d'un niveau pour un utilisateur | ❌ |

### 📚 Niveaux
| Méthode | Endpoint | Description | Auth Requise |
|---------|----------|-------------|--------------|
| `GET` | `/api/levels` | Liste de tous les niveaux | ❌ |
| `GET` | `/api/levels/category/{category}` | Niveaux par catégorie | ❌ |
| `GET` | `/api/levels/{id}/exercises` | Exercices d'un niveau | ❌ |

### ⭐ Favoris
| Méthode | Endpoint | Description | Auth Requise |
|---------|----------|-------------|--------------|
| `GET` | `/api/favorites` | Liste des favoris de l'utilisateur | ✅ |
| `POST` | `/api/favorites` | Ajouter un exercice aux favoris | ✅ |
| `DELETE` | `/api/favorites/exercise/{exerciseId}` | Retirer un exercice des favoris | ✅ |

### 📝 Exercices Utilisateur
| Méthode | Endpoint | Description | Auth Requise |
|---------|----------|-------------|--------------|
| `POST` | `/api/user-exercises/{exerciseId}/complete` | Marquer un exercice comme complété | ✅ |
| `POST` | `/api/user-exercises/{exerciseId}/progress` | Mettre à jour la progression d'un exercice | ✅ |

### 👤 Profil
| Méthode | Endpoint | Description | Auth Requise |
|---------|----------|-------------|--------------|
| `GET` | `/api/profile` | Profil de l'utilisateur authentifié | ✅ |
| `PUT` | `/api/user/goals` | Mettre à jour les objectifs utilisateur | ✅ |
| `POST` | `/api/profile/avatar` | Mettre à jour l'avatar utilisateur | ✅ |

### 🆘 Support
| Méthode | Endpoint | Description | Auth Requise |
|---------|----------|-------------|--------------|
| `POST` | `/api/support-requests` | Créer une demande de support | ✅ |
| `GET` | `/api/support-requests` | Liste des demandes de support de l'utilisateur | ✅ |

### 🔄 Version
| Méthode | Endpoint | Description | Auth Requise |
|---------|----------|-------------|--------------|
| `GET` | `/api/version-check` | Vérification de version de l'application | ❌ |

---

## 🚀 Propositions d'Endpoints pour le Dashboard Web

### 📊 Statistiques Globales (Admin)

#### 1. Vue d'ensemble du système
```
GET /api/admin/dashboard/overview
```
**Retourne :**
- Nombre total d'utilisateurs
- Nombre d'utilisateurs actifs (derniers 7/30 jours)
- Nombre total d'exercices complétés
- XP total distribué
- Temps total d'entraînement
- Taux de rétention (DAU/MAU)
- Nombre de nouvelles inscriptions (jour/semaine/mois)

#### 2. Statistiques d'engagement
```
GET /api/admin/dashboard/engagement?period=week|month|year
```
**Retourne :**
- Graphique d'activité quotidienne (nombre d'exercices complétés par jour)
- Distribution des utilisateurs par niveau d'XP
- Top 10 exercices les plus populaires
- Taux de complétion moyen par exercice
- Temps moyen d'entraînement par utilisateur

#### 3. Statistiques utilisateurs
```
GET /api/admin/dashboard/users/stats
```
**Retourne :**
- Répartition par tranche d'XP
- Répartition par temps d'entraînement
- Nombre d'utilisateurs par niveau atteint
- Utilisateurs les plus actifs (top 20)
- Utilisateurs inactifs (derniers 30 jours)

---

### 👥 Gestion des Utilisateurs (Admin)

#### 4. Liste des utilisateurs avec filtres
```
GET /api/admin/users?page=1&per_page=20&search=&sort_by=created_at&order=desc
```
**Paramètres :**
- `page` : Numéro de page
- `per_page` : Nombre d'éléments par page
- `search` : Recherche par nom/email
- `sort_by` : Champ de tri (created_at, total_xp, total_training_time)
- `order` : asc/desc
- `role` : Filtrer par rôle
- `active` : Filtrer les utilisateurs actifs/inactifs

**Retourne :**
- Liste paginée des utilisateurs avec :
  - ID, nom, email, téléphone
  - Date d'inscription
  - XP total, temps d'entraînement
  - Nombre de vidéos complétées
  - Dernière activité
  - Statut (actif/inactif)

#### 5. Détails d'un utilisateur
```
GET /api/admin/users/{userId}
```
**Retourne :**
- Informations complètes de l'utilisateur
- Profil détaillé (XP, temps, vidéos complétées)
- Historique des exercices complétés
- Graphique de progression (XP au fil du temps)
- Liste des favoris
- Demandes de support associées

#### 6. Modifier un utilisateur
```
PUT /api/admin/users/{userId}
```
**Body :**
```json
{
  "full_name": "string",
  "email": "string",
  "phone": "string",
  "role": "player|admin"
}
```

#### 7. Supprimer un utilisateur
```
DELETE /api/admin/users/{userId}
```

#### 8. Désactiver/Réactiver un utilisateur
```
POST /api/admin/users/{userId}/toggle-status
```

#### 9. Réinitialiser les statistiques d'un utilisateur
```
POST /api/admin/users/{userId}/reset-stats
```

---

### 🏋️ Gestion des Exercices (Admin)

#### 10. Liste des exercices avec statistiques
```
GET /api/admin/exercises?page=1&per_page=20&level_id=&search=
```
**Retourne :**
- Liste paginée des exercices avec :
  - ID, titre, description
  - Niveau associé
  - Durée, XP value
  - Nombre de complétions
  - Taux de complétion
  - Temps moyen de visionnage

#### 11. Créer un exercice
```
POST /api/admin/exercises
```
**Body :**
```json
{
  "level_id": "uuid",
  "title": "string",
  "description": "string",
  "video_url": "string",
  "banner_url": "string",
  "duration": "integer",
  "xp_value": "integer"
}
```

#### 12. Modifier un exercice
```
PUT /api/admin/exercises/{exerciseId}
```

#### 13. Supprimer un exercice
```
DELETE /api/admin/exercises/{exerciseId}
```

#### 14. Statistiques d'un exercice
```
GET /api/admin/exercises/{exerciseId}/stats
```
**Retourne :**
- Nombre total de complétions
- Nombre d'utilisateurs uniques
- Taux de complétion
- Temps moyen de visionnage
- Distribution des complétions dans le temps
- Top utilisateurs pour cet exercice

---

### 📚 Gestion des Niveaux (Admin)

#### 15. Liste des niveaux avec statistiques
```
GET /api/admin/levels?category=
```
**Retourne :**
- Liste des niveaux avec :
  - Nombre d'exercices par niveau
  - Nombre d'utilisateurs ayant complété le niveau
  - Taux de complétion moyen

#### 16. Créer un niveau
```
POST /api/admin/levels
```
**Body :**
```json
{
  "name": "string",
  "category": "string",
  "level_number": "integer",
  "description": "string",
  "banner_url": "string"
}
```

#### 17. Modifier un niveau
```
PUT /api/admin/levels/{levelId}
```

#### 18. Supprimer un niveau
```
DELETE /api/admin/levels/{levelId}
```

---

### 🆘 Gestion du Support (Admin)

#### 19. Liste de toutes les demandes de support
```
GET /api/admin/support-requests?status=pending|resolved|all&page=1&per_page=20
```
**Retourne :**
- Liste paginée avec :
  - ID, message
  - Utilisateur associé (nom, email)
  - Date de création
  - Statut (pending/resolved)
  - Réponse (si résolu)

#### 20. Détails d'une demande de support
```
GET /api/admin/support-requests/{requestId}
```

#### 21. Répondre à une demande de support
```
POST /api/admin/support-requests/{requestId}/respond
```
**Body :**
```json
{
  "response": "string",
  "status": "resolved"
}
```

#### 22. Marquer comme résolu/en attente
```
PUT /api/admin/support-requests/{requestId}/status
```
**Body :**
```json
{
  "status": "pending|resolved"
}
```

---

### 📈 Rapports et Analytics (Admin)

#### 23. Rapport d'activité quotidienne
```
GET /api/admin/reports/daily-activity?start_date=&end_date=
```
**Retourne :**
- Nombre d'exercices complétés par jour
- Nombre de nouveaux utilisateurs par jour
- XP distribué par jour
- Temps d'entraînement total par jour

#### 24. Rapport de rétention
```
GET /api/admin/reports/retention?period=week|month
```
**Retourne :**
- Taux de rétention par cohorte
- Graphique de rétention
- Utilisateurs actifs vs inactifs

#### 25. Rapport de performance des exercices
```
GET /api/admin/reports/exercises-performance?level_id=
```
**Retourne :**
- Classement des exercices par popularité
- Exercices avec le meilleur taux de complétion
- Exercices les moins complétés
- Temps moyen par exercice

#### 26. Export de données
```
GET /api/admin/export/users?format=csv|json
GET /api/admin/export/exercises?format=csv|json
GET /api/admin/export/user-exercises?format=csv|json&start_date=&end_date=
```

---

### 🔔 Notifications et Communications (Admin)

#### 27. Envoyer une notification push (si implémenté)
```
POST /api/admin/notifications/send
```
**Body :**
```json
{
  "user_ids": ["uuid1", "uuid2"] | "all",
  "title": "string",
  "message": "string",
  "type": "info|warning|success"
}
```

#### 28. Historique des notifications
```
GET /api/admin/notifications?page=1&per_page=20
```

---

### ⚙️ Configuration Système (Admin)

#### 29. Paramètres de l'application
```
GET /api/admin/settings
PUT /api/admin/settings
```
**Retourne/Modifie :**
- Version de l'application
- Paramètres de maintenance
- Limites de l'API
- Configuration des récompenses XP

#### 30. Logs système
```
GET /api/admin/logs?level=error|warning|info&page=1&per_page=50
```

---

## 🔒 Sécurité et Permissions

### Middleware à créer :
- `admin` : Vérifie que l'utilisateur a le rôle `admin`
- `throttle` : Limite les requêtes pour éviter les abus

### Rôles suggérés :
- `player` : Utilisateur standard (rôle actuel)
- `admin` : Administrateur avec accès au dashboard
- `moderator` : Modérateur avec accès limité (support, utilisateurs)

---

## 📝 Notes d'Implémentation

1. **Pagination** : Tous les endpoints de liste devraient supporter la pagination
2. **Filtres** : Implémenter des filtres avancés pour faciliter la recherche
3. **Cache** : Mettre en cache les statistiques globales (ex: Redis)
4. **Queue** : Utiliser des queues pour les exports de données volumineux
5. **Validation** : Valider toutes les entrées avec des Form Requests
6. **Documentation** : Utiliser Swagger/OpenAPI pour documenter l'API
7. **Tests** : Créer des tests pour tous les nouveaux endpoints admin

---

## 🎯 Priorités Recommandées

### Phase 1 (Essentiel)
1. Authentification admin
2. Vue d'ensemble du dashboard
3. Liste et détails des utilisateurs
4. Liste des demandes de support avec réponse

### Phase 2 (Important)
5. Statistiques d'engagement
6. Gestion des exercices (CRUD)
7. Gestion des niveaux (CRUD)
8. Rapports d'activité

### Phase 3 (Amélioration)
9. Export de données
10. Notifications
11. Logs système
12. Configuration avancée



