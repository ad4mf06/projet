# 🛡️ Agent Claude — Auditeur de Sécurité OWASP

## Vue d'ensemble

Cet agent Claude analyse la sécurité d'une application web selon les conventions **OWASP Top 10** (2021). Il inspecte le code source, les configurations, les endpoints API, et produit un rapport structuré avec niveaux de criticité, preuves de vulnérabilité, et recommandations correctives.

---

## Prompt Système (System Prompt)

```
Tu es un expert en cybersécurité spécialisé dans l'audit d'applications web selon le référentiel OWASP Top 10 (2021).

Ton rôle est d'analyser le code, les configurations, les routes API ou toute autre information fournie par l'utilisateur, afin d'identifier des vulnérabilités de sécurité selon les 10 catégories OWASP.

### Comportement attendu :

1. **Analyse méthodique** : Parcours chaque catégorie OWASP pertinente pour le contexte fourni.
2. **Identification précise** : Pour chaque vulnérabilité détectée, indique :
   - La catégorie OWASP correspondante (ex: A03:2021 - Injection)
   - Le niveau de criticité : 🔴 Critique | 🟠 Élevé | 🟡 Moyen | 🟢 Faible | ℹ️ Informatif
   - La preuve (extrait de code ou configuration concernée)
   - L'impact potentiel
   - La recommandation corrective avec exemple de code si applicable
3. **Rapport structuré** : Génère un rapport clair, organisé par catégorie OWASP.
4. **Non-jugement** : Tu analyses uniquement dans un but défensif et éducatif.
5. **Exhaustivité** : Ne saute aucune catégorie OWASP sans raison documentée.

### Format de réponse :
Utilise toujours ce format pour chaque vulnérabilité détectée :

---
**[A0X:2021 - Nom de la catégorie]**
- **Criticité** : 🔴/🟠/🟡/🟢/ℹ️
- **Localisation** : fichier/fonction/endpoint concerné
- **Preuve** : `code ou configuration vulnérable`
- **Impact** : description de l'impact
- **Correction** : recommandation + exemple de code corrigé
---

Si aucune vulnérabilité n'est détectée pour une catégorie, indique explicitement : ✅ Aucune vulnérabilité détectée.

Commence toujours par demander à l'utilisateur de fournir : le code source, les fichiers de configuration, les routes API, ou une description de l'architecture de l'application.
```

---

## Architecture de l'Agent

```
┌─────────────────────────────────────────────────────────┐
│                    UTILISATEUR                          │
│         (code source / config / description)            │
└──────────────────────┬──────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────┐
│              AGENT CLAUDE (OWASP Auditor)               │
│                                                         │
│  ┌─────────────┐  ┌─────────────┐  ┌───────────────┐   │
│  │  Analyse    │  │  Moteur de  │  │   Générateur  │   │
│  │  du contexte│→ │  détection  │→ │   de rapport  │   │
│  │             │  │  OWASP      │  │               │   │
│  └─────────────┘  └─────────────┘  └───────────────┘   │
│                                                         │
│  Référentiels : OWASP Top 10 (2021) + CWE + CVSS       │
└──────────────────────┬──────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────┐
│                 RAPPORT D'AUDIT                         │
│    Score global | Vulnérabilités | Recommandations      │
└─────────────────────────────────────────────────────────┘
```

---

## Couverture OWASP Top 10 (2021)

| ID | Catégorie | Description | Vecteurs analysés |
|----|-----------|-------------|-------------------|
| **A01** | Broken Access Control | Contrôles d'accès insuffisants | Rôles, permissions, IDOR, CORS |
| **A02** | Cryptographic Failures | Mauvaise gestion de la cryptographie | Chiffrement, secrets, TLS, hachage |
| **A03** | Injection | Injections SQL, NoSQL, OS, LDAP | Requêtes, commandes, expressions |
| **A04** | Insecure Design | Défauts de conception | Architecture, flux métier, menaces |
| **A05** | Security Misconfiguration | Mauvaises configurations | Headers HTTP, erreurs, defaults |
| **A06** | Vulnerable Components | Composants vulnérables | Dépendances, CVE, versions |
| **A07** | Auth Failures | Problèmes d'authentification | Sessions, MFA, brute force |
| **A08** | Software Integrity | Intégrité logicielle | CI/CD, packages, désérialisation |
| **A09** | Logging Failures | Journalisation insuffisante | Logs, alertes, monitoring |
| **A10** | SSRF | Falsification de requêtes côté serveur | Fetch, webhooks, redirections |

---

## Implémentation — Code de l'Agent

### Version JavaScript (Node.js / API Anthropic)

```javascript
import Anthropic from "@anthropic-ai/sdk";
import * as fs from "fs";
import * as readline from "readline";

const client = new Anthropic({
  apiKey: process.env.ANTHROPIC_API_KEY,
});

const SYSTEM_PROMPT = `Tu es un expert en cybersécurité spécialisé dans l'audit d'applications web selon le référentiel OWASP Top 10 (2021).

Ton rôle est d'analyser le code, les configurations, les routes API ou toute autre information fournie par l'utilisateur, afin d'identifier des vulnérabilités de sécurité selon les 10 catégories OWASP.

Pour chaque vulnérabilité détectée, tu dois fournir :
- La catégorie OWASP (ex: A03:2021 - Injection)
- Le niveau de criticité : 🔴 Critique | 🟠 Élevé | 🟡 Moyen | 🟢 Faible | ℹ️ Informatif
- La localisation précise (fichier, fonction, ligne)
- La preuve (extrait de code vulnérable)
- L'impact potentiel
- La recommandation corrective avec exemple de code corrigé

Génère un rapport structuré par catégorie OWASP avec un score de risque global à la fin.
Si aucune vulnérabilité n'est trouvée pour une catégorie, indique : ✅ Aucune vulnérabilité détectée.`;

const conversationHistory = [];

async function analyzeSecurityOWASP(userInput) {
  conversationHistory.push({
    role: "user",
    content: userInput,
  });

  const response = await client.messages.create({
    model: "claude-opus-4-5",
    max_tokens: 8192,
    system: SYSTEM_PROMPT,
    messages: conversationHistory,
  });

  const assistantMessage = response.content[0].text;

  conversationHistory.push({
    role: "assistant",
    content: assistantMessage,
  });

  return assistantMessage;
}

async function loadFileForAnalysis(filePath) {
  try {
    const content = fs.readFileSync(filePath, "utf-8");
    return `Analyse ce fichier pour des vulnérabilités OWASP :\n\nFichier : ${filePath}\n\`\`\`\n${content}\n\`\`\``;
  } catch (error) {
    return null;
  }
}

async function generateAuditReport(outputPath) {
  const reportRequest = `Génère maintenant un rapport d'audit complet en Markdown avec :
1. Un résumé exécutif
2. Le score de risque global (sur 10)
3. La liste de toutes les vulnérabilités par criticité
4. Un plan de remédiation priorisé
5. Les recommandations de sécurité générales`;

  const report = await analyzeSecurityOWASP(reportRequest);

  if (outputPath) {
    fs.writeFileSync(outputPath, `# Rapport d'Audit OWASP\n\nDate : ${new Date().toLocaleDateString("fr-FR")}\n\n${report}`);
    console.log(`\n📄 Rapport sauvegardé : ${outputPath}`);
  }

  return report;
}

async function main() {
  console.log("🛡️  Agent Auditeur OWASP - Sécurité Application Web");
  console.log("=".repeat(55));
  console.log("Commandes : 'fichier:<chemin>' | 'rapport' | 'quitter'\n");

  const rl = readline.createInterface({
    input: process.stdin,
    output: process.stdout,
  });

  // Message d'accueil
  const welcome = await analyzeSecurityOWASP(
    "Présente-toi brièvement et explique comment tu vas procéder pour l'audit OWASP. Demande ensuite ce que l'utilisateur souhaite analyser."
  );
  console.log(`\n🤖 Agent : ${welcome}\n`);

  const askQuestion = () => {
    rl.question("👤 Vous : ", async (input) => {
      const trimmed = input.trim();

      if (trimmed.toLowerCase() === "quitter") {
        console.log("\n✅ Audit terminé. Au revoir !");
        rl.close();
        return;
      }

      if (trimmed.toLowerCase() === "rapport") {
        console.log("\n📊 Génération du rapport final...");
        const report = await generateAuditReport(`audit_owasp_${Date.now()}.md`);
        console.log(`\n🤖 Agent :\n${report}\n`);
      } else if (trimmed.startsWith("fichier:")) {
        const filePath = trimmed.replace("fichier:", "").trim();
        const fileContent = await loadFileForAnalysis(filePath);
        if (fileContent) {
          console.log(`\n📁 Analyse de ${filePath}...`);
          const analysis = await analyzeSecurityOWASP(fileContent);
          console.log(`\n🤖 Agent :\n${analysis}\n`);
        } else {
          console.log(`\n❌ Impossible de lire le fichier : ${filePath}\n`);
        }
      } else if (trimmed) {
        const response = await analyzeSecurityOWASP(trimmed);
        console.log(`\n🤖 Agent :\n${response}\n`);
      }

      askQuestion();
    });
  };

  askQuestion();
}

main().catch(console.error);
```

---

### Version Python

```python
import os
import sys
from datetime import datetime
from anthropic import Anthropic

client = Anthropic(api_key=os.environ.get("ANTHROPIC_API_KEY"))

SYSTEM_PROMPT = """Tu es un expert en cybersécurité spécialisé dans l'audit OWASP Top 10 (2021).

Analyse chaque élément fourni et identifie les vulnérabilités selon ces catégories :
- A01: Broken Access Control
- A02: Cryptographic Failures  
- A03: Injection
- A04: Insecure Design
- A05: Security Misconfiguration
- A06: Vulnerable and Outdated Components
- A07: Identification and Authentication Failures
- A08: Software and Data Integrity Failures
- A09: Security Logging and Monitoring Failures
- A10: Server-Side Request Forgery (SSRF)

Pour chaque vulnérabilité, fournis :
- Catégorie OWASP | Criticité (🔴🟠🟡🟢) | Localisation | Preuve | Impact | Correction
"""

conversation_history = []

def analyze(user_input: str) -> str:
    conversation_history.append({"role": "user", "content": user_input})
    
    response = client.messages.create(
        model="claude-opus-4-5",
        max_tokens=8192,
        system=SYSTEM_PROMPT,
        messages=conversation_history
    )
    
    assistant_msg = response.content[0].text
    conversation_history.append({"role": "assistant", "content": assistant_msg})
    return assistant_msg

def load_file(path: str) -> str | None:
    try:
        with open(path, 'r', encoding='utf-8') as f:
            content = f.read()
        return f"Analyse ce fichier :\n\nFichier : {path}\n```\n{content}\n```"
    except Exception:
        return None

def save_report(content: str):
    filename = f"audit_owasp_{datetime.now().strftime('%Y%m%d_%H%M%S')}.md"
    with open(filename, 'w', encoding='utf-8') as f:
        f.write(f"# Rapport d'Audit OWASP\n\nDate : {datetime.now().strftime('%d/%m/%Y %H:%M')}\n\n{content}")
    print(f"\n📄 Rapport sauvegardé : {filename}")

def main():
    print("🛡️  Agent Auditeur OWASP — Sécurité Application Web")
    print("=" * 52)
    print("Commandes : 'fichier:<chemin>' | 'rapport' | 'quitter'\n")

    welcome = analyze("Présente-toi et demande ce que l'utilisateur souhaite auditer.")
    print(f"\n🤖 Agent : {welcome}\n")

    while True:
        user_input = input("👤 Vous : ").strip()

        if not user_input:
            continue
        elif user_input.lower() == "quitter":
            print("\n✅ Audit terminé.")
            break
        elif user_input.lower() == "rapport":
            print("\n📊 Génération du rapport...")
            report = analyze("Génère un rapport d'audit Markdown complet avec résumé exécutif, score global, vulnérabilités par criticité et plan de remédiation.")
            save_report(report)
            print(f"\n🤖 Agent :\n{report}\n")
        elif user_input.startswith("fichier:"):
            path = user_input.replace("fichier:", "").strip()
            content = load_file(path)
            if content:
                print(f"\n📁 Analyse de {path}...")
                print(f"\n🤖 Agent :\n{analyze(content)}\n")
            else:
                print(f"\n❌ Fichier introuvable : {path}\n")
        else:
            print(f"\n🤖 Agent :\n{analyze(user_input)}\n")

if __name__ == "__main__":
    main()
```

---

## Exemples d'Interactions

### Exemple 1 — Analyse d'une fonction d'authentification

**Entrée utilisateur :**
```python
def login(username, password):
    query = f"SELECT * FROM users WHERE username='{username}' AND password='{password}'"
    result = db.execute(query)
    if result:
        session['user'] = username
        return True
```

**Réponse de l'agent :**

---
**[A03:2021 — Injection SQL]**
- **Criticité** : 🔴 Critique
- **Localisation** : `login()` — construction de la requête SQL
- **Preuve** : `f"SELECT * FROM users WHERE username='{username}' AND password='{password}'"`
- **Impact** : Contournement total de l'authentification, dump de la base de données, exécution de commandes OS selon le SGBD
- **Correction** :
```python
def login(username, password):
    query = "SELECT * FROM users WHERE username = %s AND password = %s"
    result = db.execute(query, (username, hash_password(password)))
    if result:
        session['user'] = username
        session.regenerate()  # Prévention fixation de session
        return True
```
---

**[A02:2021 — Cryptographic Failures]**
- **Criticité** : 🔴 Critique
- **Localisation** : Comparaison du mot de passe en clair dans la requête SQL
- **Impact** : Les mots de passe sont stockés ou comparés en clair — exposition totale en cas de fuite
- **Correction** : Utiliser `bcrypt` ou `argon2` pour le hachage des mots de passe

---

### Exemple 2 — Analyse d'un fichier de configuration

**Entrée utilisateur :** `fichier:config/app.yaml`

```yaml
database:
  host: localhost
  password: admin123
  
debug: true
secret_key: "dev-secret-key"

cors:
  origins: "*"
```

**Réponse de l'agent :**

---
**[A05:2021 — Security Misconfiguration]**
- **Criticité** : 🔴 Critique (×3)

| Problème | Impact | Correction |
|----------|--------|------------|
| `debug: true` en production | Exposition des stack traces et routes internes | `debug: false` + variable d'environnement |
| `secret_key: "dev-secret-key"` | Falsification de tokens/sessions | Clé aléatoire ≥ 256 bits via `secrets.token_hex(32)` |
| `cors: origins: "*"` | Cross-Origin requests non contrôlées | Lister explicitement les domaines autorisés |

**[A02:2021 — Cryptographic Failures]**
- **Criticité** : 🔴 Critique
- `password: admin123` — Mot de passe de base de données en clair dans le fichier de config
- **Correction** : Utiliser des variables d'environnement ou un gestionnaire de secrets (Vault, AWS Secrets Manager)
---

---

## Grille de Scoring CVSS Simplifié

| Score | Criticité | Délai de remédiation recommandé |
|-------|-----------|----------------------------------|
| 9.0 – 10.0 | 🔴 Critique | Immédiat (< 24h) |
| 7.0 – 8.9 | 🟠 Élevé | Court terme (< 7 jours) |
| 4.0 – 6.9 | 🟡 Moyen | Moyen terme (< 30 jours) |
| 0.1 – 3.9 | 🟢 Faible | Long terme (< 90 jours) |
| 0.0 | ℹ️ Informatif | Amélioration continue |

---

## Checklist d'Audit par Catégorie OWASP

### A01 — Broken Access Control
- [ ] Vérification des contrôles d'accès sur chaque route/endpoint
- [ ] Absence de références directes à des objets internes (IDOR)
- [ ] Configuration CORS restrictive
- [ ] Principe du moindre privilège appliqué
- [ ] Interdiction d'accès aux métadonnées de répertoires

### A02 — Cryptographic Failures
- [ ] Données sensibles chiffrées au repos et en transit
- [ ] Algorithmes de chiffrement modernes (AES-256, RSA-2048+)
- [ ] Hachage des mots de passe avec sel (bcrypt, argon2, scrypt)
- [ ] Pas de secrets en dur dans le code
- [ ] Certificats TLS valides et à jour

### A03 — Injection
- [ ] Requêtes SQL paramétrées (prepared statements)
- [ ] Validation et assainissement de toutes les entrées
- [ ] Pas d'exécution de commandes OS avec des données utilisateur
- [ ] Protection XSS (encodage des sorties)
- [ ] Validation LDAP et XPath

### A04 — Insecure Design
- [ ] Modélisation des menaces réalisée (STRIDE/DREAD)
- [ ] Flux métier critique protégés contre les abus
- [ ] Rate limiting sur les opérations sensibles
- [ ] Principe de défense en profondeur

### A05 — Security Misconfiguration
- [ ] Mode debug désactivé en production
- [ ] Headers de sécurité HTTP configurés (CSP, HSTS, X-Frame-Options)
- [ ] Comptes et mots de passe par défaut modifiés
- [ ] Gestion des erreurs sans fuite d'information
- [ ] Services et ports inutiles désactivés

### A06 — Vulnerable Components
- [ ] Inventaire des dépendances à jour
- [ ] Aucun composant avec CVE critique connue
- [ ] Processus de mise à jour automatisé
- [ ] Scan régulier avec OWASP Dependency-Check ou Snyk

### A07 — Auth Failures
- [ ] Authentification multi-facteurs disponible
- [ ] Politique de mots de passe robuste
- [ ] Protection contre les attaques brute force
- [ ] Sessions invalidées à la déconnexion
- [ ] Tokens JWT signés et validés correctement

### A08 — Software Integrity
- [ ] Vérification d'intégrité des packages (checksums, signatures)
- [ ] Pipeline CI/CD sécurisé
- [ ] Désérialisation sécurisée
- [ ] Absence de mises à jour automatiques depuis sources non fiables

### A09 — Logging Failures
- [ ] Journalisation des événements d'authentification
- [ ] Logs des accès refusés et erreurs de sécurité
- [ ] Pas de données sensibles dans les logs
- [ ] Alertes configurées pour les incidents
- [ ] Logs centralisés et protégés contre la falsification

### A10 — SSRF
- [ ] Validation et filtrage des URLs fournis par l'utilisateur
- [ ] Blocage des adresses IP internes (RFC 1918)
- [ ] Utilisation d'une liste blanche de domaines autorisés
- [ ] Désactivation des redirections HTTP non nécessaires

---

## Installation et Prérequis

### JavaScript / Node.js

```bash
# Prérequis
node --version  # >= 18.0.0

# Installation
npm install @anthropic-ai/sdk

# Configuration
export ANTHROPIC_API_KEY="votre-clé-api"

# Lancement
node owasp_agent.mjs
```

### Python

```bash
# Prérequis  
python --version  # >= 3.11

# Installation
pip install anthropic

# Configuration
export ANTHROPIC_API_KEY="votre-clé-api"

# Lancement
python owasp_agent.py
```

---

## Paramètres de Configuration

| Paramètre | Valeur par défaut | Description |
|-----------|-------------------|-------------|
| `model` | `claude-opus-4-5` | Modèle Claude utilisé |
| `max_tokens` | `8192` | Longueur maximale des réponses |
| `temperature` | `0` (implicite) | Déterminisme des analyses |

---

## Limites et Avertissements

> ⚠️ **Usage éthique uniquement** : Cet agent est conçu pour des audits de sécurité défensifs sur des systèmes dont vous êtes propriétaire ou pour lesquels vous avez une autorisation explicite.

> ⚠️ **Complément humain** : L'agent ne remplace pas un audit de sécurité professionnel. Les résultats doivent être validés par un expert humain.

> ⚠️ **Contexte limité** : L'agent analyse uniquement ce qui lui est fourni. Une application complète nécessite une analyse exhaustive de tous ses composants.

---

## Références

- [OWASP Top 10 — 2021](https://owasp.org/Top10/)
- [OWASP Testing Guide v4.2](https://owasp.org/www-project-web-security-testing-guide/)
- [CWE/SANS Top 25](https://cwe.mitre.org/top25/)
- [CVSS v3.1 Calculator](https://www.first.org/cvss/calculator/3.1)
- [Documentation Anthropic API](https://docs.anthropic.com)

---

*Agent OWASP Security Auditor — Propulsé par Claude (Anthropic)*
