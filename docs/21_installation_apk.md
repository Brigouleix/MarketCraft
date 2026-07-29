# Installation de l'application mobile (APK)

Cette note décrit l'installation de l'application mobile **MarketCraft** (React Native) à partir de l'APK de démonstration.

## Fichier livré

```
marketcraft-mobile/android/app/build/outputs/apk/release/app-release.apk
```

- **Type** : APK *release* (le code JavaScript est embarqué → l'application fonctionne **en autonomie**, sans serveur de développement).
- **Taille** : ~53 Mo.
- **Signature** : clé de *debug* du projet (suffisant pour une démonstration ; une clé de *release* dédiée serait requise pour une publication sur le Play Store).
- **Compatibilité** : Android **6.0 (API 23)** ou supérieur.

> L'API doit être accessible pour que l'application affiche des données (catalogue, connexion, commandes). Démarrer le back-end au préalable : `php artisan serve --port=8000` dans `marketcraft-api/`.

---

## Méthode 1 — Émulateur Android (recommandée pour la soutenance)

L'application est configurée pour joindre l'API de la machine hôte via l'adresse `http://10.0.2.2:8000` (adresse réservée par l'émulateur Android pour désigner `localhost` du PC). **Aucune modification n'est nécessaire.**

1. Démarrer le back-end : `php artisan serve --port=8000`.
2. Lancer un émulateur depuis **Android Studio → Device Manager**.
3. Installer l'APK, au choix :
   - **glisser-déposer** le fichier `app-release.apk` sur la fenêtre de l'émulateur ;
   - **ou** en ligne de commande :
     ```bash
     adb install android/app/build/outputs/apk/release/app-release.apk
     ```
4. Ouvrir l'application **MarketCraft** depuis le tiroir d'applications.

---

## Méthode 2 — Téléphone Android réel

Sur un vrai téléphone, `10.0.2.2` n'a pas de sens : il faut pointer l'application vers l'**IP de la machine** qui héberge l'API, sur le même réseau Wi-Fi.

1. **Adapter l'URL de l'API** dans [`marketcraft-mobile/src/services/api.ts`](../marketcraft-mobile/src/services/api.ts) :
   ```ts
   const BASE_URL = 'http://192.168.X.X:8000/api'; // IP locale du PC
   ```
   (récupérer l'IP avec `ipconfig` sous Windows).
2. **Reconstruire** l'APK :
   ```powershell
   $env:JAVA_HOME = "C:\Program Files\Android\Android Studio\jbr"
   cd marketcraft-mobile\android
   .\gradlew assembleRelease
   ```
3. Autoriser sur le téléphone l'**installation d'applications de sources inconnues** (Paramètres → Sécurité).
4. **Transférer** l'APK sur le téléphone (câble USB, `adb install`, ou lien de téléchargement) et l'ouvrir pour l'installer.
5. S'assurer que le téléphone et le PC sont sur le **même réseau Wi-Fi**, et que le back-end tourne.

---

## Reconstruire l'APK depuis les sources

```powershell
cd marketcraft-mobile
npm install
$env:JAVA_HOME = "C:\Program Files\Android\Android Studio\jbr"
cd android
.\gradlew assembleRelease
```

Le JDK 21 embarqué avec Android Studio (`jbr`) est utilisé volontairement : le Gradle de React Native 0.74 n'est pas compatible avec les JDK plus récents installés sur la machine.

---

## Dépannage

| Symptôme | Cause probable | Solution |
|---|---|---|
| Écran rouge « Unable to load script » | APK **debug** installé (JS servi par Metro) | Utiliser l'APK **release** (JS embarqué) |
| Catalogue / connexion vides, erreurs réseau | API injoignable | Démarrer `php artisan serve` ; vérifier l'URL (`10.0.2.2` en émulateur, IP locale sur téléphone) |
| « App non installée » | Version déjà présente signée différemment | Désinstaller l'ancienne version avant de réinstaller |
| Installation bloquée sur téléphone | Sources inconnues non autorisées | Autoriser l'installation hors store dans les paramètres |
