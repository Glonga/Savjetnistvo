=== Savjetništvo ===
Contributors: Glonga
Tags: writing, coaching, mentorship, projects, tasks
Requires at least: 5.8
Tested up to: 6.6
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Plugin za vođenje online savjetništva pri pisanju: upravljanje klijentima, projektima, susretima i zadaćama, s portalom za klijente.

== Description ==

**Savjetništvo** je WordPress plugin razvijen za autore, mentore i izdavače koji vode projekte kreativnog pisanja i žele sustav za:
- upravljanje klijentima (uloga *Klijent*),
- praćenje projekata (vrsta djela, sinopsis, datumi, program, cijena, popusti),
- bilježenje susreta i zadaća,
- praćenje plaćanja,
- klijentski portal za predaju i pregled zadaća.

Trenutno dostupne značajke:
- CPT **Projekt** ("Projekti pisanja") s metaboxom *Postavke projekta* (klijent, savjetnik, datumi, program, cijena, popust) i evidencijom susreta.
- Uloge **Klijent** i **Savjetnik**.
- Prošireni korisnički profil (pseudonim, telefon).
- Shortcode `[savjetnistvo_portal]` → klijentski portal sa sekcijama *Moji podaci* i *Moji projekti*.
- REST API rute (`/sv/v1/me`, `/sv/v1/projects`) za dohvat podataka.

Planirane nadogradnje:
- CPT **Susret** i **Zadaća** (predaja i pregled zadaća, statusi).
- CPT **Plaćanje**.
- E-mail podsjetnici (susreti, rokovi, plaćanja).
- Admin dashboard s pregledom svih klijenata i projekata.

== Installation ==

1. Upload folder `savjetnistvo` u `/wp-content/plugins/`.
2. Aktiviraj plugin u **Plugins** meniju u WordPressu.
3. Nakon aktivacije, uloge **Klijent** i **Savjetnik** postaju dostupne pod *Users → Add New*.
4. Kreiraj projekt pod *Projekti pisanja* i poveži ga s klijentom.
5. Umetni `[savjetnistvo_portal]` shortcode na stranicu gdje klijenti mogu vidjeti svoje podatke i projekte.

== Frequently Asked Questions ==

= Gdje klijent vidi svoj portal? =
Napravi stranicu i u nju dodaj `[savjetnistvo_portal]`. Kad se klijent prijavi, vidjet će sekcije *Moji podaci* i *Moji projekti*.

= Što ako u padajućem izborniku nema klijenata? =
Provjeri da je korisnik dodan s ulogom **Klijent** (Users → Add New → Role: Klijent). Ako role nema, deaktiviraj i ponovno aktiviraj plugin.

= Mogu li koristiti plugin na multisite instalaciji? =
Plugin je tehnički kompatibilan, ali nije testiran za multisite okruženja.

== Screenshots ==

1. Admin → Dodaj novi projekt → Metabox *Postavke projekta* (odabir klijenta, savjetnika, program, datumi, popust).
2. Klijentski portal s prikazom osobnih podataka i popisom projekata.

== Changelog ==

= 0.1.0 =
* Prva verzija
* CPT Projekt s metaboxom
* Role Klijent i Savjetnik
* Polja u korisničkom profilu (pseudonim, telefon)
* Shortcode `[savjetnistvo_portal]`
* REST rute za “me” i “projects”

== Upgrade Notice ==

= 0.1.0 =
Prva verzija plugina, radna osnova za daljnji razvoj.
