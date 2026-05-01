package embrun.fr.tournoibridgeonline.screens

import android.app.Activity
import android.content.pm.ActivityInfo
import android.util.Log
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import embrun.fr.tournoibridgeonline.common.EcranPleinScaffold
import embrun.fr.tournoibridgeonline.common.model.Carte
import embrun.fr.tournoibridgeonline.common.model.AnnonceJoueur


@Composable
fun AffichageDonneEcrant(
    idTournoi: Int,
    dateTournoi: String,
    numeroDonne: Int,
    mains: Map<String, List<Carte>>,
    encheres: List<AnnonceJoueur>,
    equipeNS: Int,
    equipeEO: Int,
    vulnerable: String,  // ✅ AJOUTER CE PARAMÈTRE
    donneur: String,        // ✅ Déjà ajouté normalement
    contrat: String = "",   // ✅ AJOUT
    declarant: String = "", // ✅ AJOUT
    onBack: () -> Unit
) {
    val context = LocalContext.current
    DisposableEffect(Unit) {
        val activity = context as? Activity
        activity?.requestedOrientation = ActivityInfo.SCREEN_ORIENTATION_PORTRAIT
        onDispose { }
    }

    EcranPleinScaffold {
        Column(modifier = Modifier.fillMaxSize().background(Color(0xFF1B5E20))) { // Tapis Vert
            // Définition d'un vert légèrement plus clair que le tapis (0xFF1B5E20)
            val vertBandeau = Color(0xFF2E7D32)
            val styleSerre = TextStyle(lineHeight = 14.sp) // Élimine l'espace vertical interne du texte

            Column(modifier = Modifier.fillMaxWidth()) {
                // --- LIGNE 1 : Navigation (Hauteur réduite au bouton) ---
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .background(vertBandeau),
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    IconButton(
                        onClick = onBack,
                        modifier = Modifier.size(32.dp) // Réduit la zone du bouton (standard 48dp)
                    ) {
                        Icon(
                            imageVector = Icons.AutoMirrored.Filled.ArrowBack,
                            contentDescription = "Back",
                            tint = Color.Red,
                            modifier = Modifier.size(30.dp)
                        )
                    }
                    Text(
                        text = "  retour au Tournoi N° $idTournoi du $dateTournoi",
                        color = Color.White,
                        fontWeight = FontWeight.SemiBold,
                        fontSize = 14.sp,
                        style = styleSerre
                    )
                }

                // --- LIGNE 2 : Équipes (Collée à la ligne 1) ---
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .background(vertBandeau)
                        .padding(bottom = 2.dp), // Très léger espace pour ne pas toucher le tapis vert
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.Center
                ) {
                    Text(
                        text = "Donne $numeroDonne : ",
                        color = Color.White,
                        fontSize = 13.sp,
                        style = styleSerre
                    )
                    Text(
                        text = "NS $equipeNS",
                        color = Color.Yellow,
                        fontWeight = FontWeight.Black,
                        fontSize = 14.sp,
                        style = styleSerre
                    )
                    Text(
                        text = " contre ",
                        color = Color.White,
                        fontSize = 13.sp,
                        style = styleSerre,
                        modifier = Modifier.padding(horizontal = 2.dp)
                    )
                    Text(
                        text = "EO $equipeEO",
                        color = Color.Cyan,
                        fontWeight = FontWeight.Black,
                        fontSize = 14.sp,
                        style = styleSerre
                    )
                }

            }

            Column(
                modifier = Modifier.fillMaxSize().padding(4.dp),
                verticalArrangement = Arrangement.SpaceBetween
            ) {
                // NORD
                Column(horizontalAlignment = Alignment.CenterHorizontally) {
                    Text("NORD", color = Color.White, fontSize = 15.sp, fontWeight = FontWeight.Bold)
                    MainHorizontaleAdaptative(mains["N"] ?: emptyList())
                }

                // CENTRE : OUEST | ENCHÈRES | EST
                Row(
                    modifier = Modifier.fillMaxWidth().weight(1f),
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.SpaceBetween
                ) {
                    Column(modifier = Modifier.padding(start = 2.dp)) {
                        Text(
                            "OUEST",
                            color = Color.White,
                            fontSize = 15.sp,
                            fontWeight = FontWeight.Bold
                        )
                        mains["O"]?.forEach { CarteHorizontale(it) }
                    }

                    TableauEncheres(
                        encheres,
                        vulnerable,
                        contrat = contrat,      // ✅ AJOUT : Passer le contrat
                        declarant = declarant,  // ✅ AJOUT : Passer le déclarant
                        donneur = donneur       // ✅ AJOUT : Passer le donneur
                    )

                    Column(modifier = Modifier.padding(end = 2.dp)) {
                        Text("EST", color = Color.White, fontSize = 15.sp, fontWeight = FontWeight.Bold)
                        mains["E"]?.forEach { CarteHorizontale(it) }
                    }
                }

                // SUD
                Column(horizontalAlignment = Alignment.CenterHorizontally) {
                    MainHorizontaleAdaptative(mains["S"] ?: emptyList())
                    Text("SUD", color = Color.White, fontSize = 15.sp, fontWeight = FontWeight.Bold)
                }
            }
        }
    }
}

@Composable
fun MainHorizontaleAdaptative(cartes: List<Carte>) {
    BoxWithConstraints(modifier = Modifier.fillMaxWidth()) {
        val largeurCarte = maxWidth / 13.5f
        Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.Center) {
            cartes.forEach { CarteVerticale(it, largeurCarte) }
        }
    }
}

@Composable
fun CarteVerticale(carte: Carte, largeur: androidx.compose.ui.unit.Dp) {
    val couleurSymbole = if (carte.couleur == "C" || carte.couleur == "K") Color.Red else Color.Black

    Surface(
        modifier = Modifier
            .width(largeur)
            .height(largeur * 1.6f)
            .padding(0.5.dp),
        shape = RoundedCornerShape(5.dp),
        border = BorderStroke(1.dp, Color.Gray),
        color = Color.White
    ) {
        Column(
            modifier = Modifier.fillMaxSize(),
            horizontalAlignment = Alignment.CenterHorizontally
            // On ne met PAS de padding vertical ici pour ne pas tronquer
        ) {
            // VALEUR EN HAUT
            Text(
                text = carte.valeur,
                fontSize = 15.sp,
                fontWeight = FontWeight.Black,
                style = TextStyle(lineHeight = 16.sp), // Force une ligne compacte
                modifier = Modifier.padding(top = 4.dp)
            )

            // LE VIDE (C'est lui qui prend toute la place centrale)
            Spacer(modifier = Modifier.weight(1f))

            // SYMBOLE EN BAS
            Text(
                text = carte.symbole,
                fontSize = 12.sp,
                color = couleurSymbole,
                style = TextStyle(lineHeight = 12.sp), // Compact
                modifier = Modifier.padding(bottom = 4.dp) // Petit décalage du bord sans tronquer
            )
        }
    }
}

@Composable
fun CarteHorizontale(carte: Carte) {
    val couleurSymbole = if (carte.couleur == "C" || carte.couleur == "K") Color.Red else Color.Black
    Surface(
        modifier = Modifier
            .width(48.dp)
            .height(26.dp)
            .padding(0.5.dp),
        shape = RoundedCornerShape(5.dp),
        border = BorderStroke(1.dp, Color.Gray),
        color = Color.White
    ) {
        Row(horizontalArrangement = Arrangement.Center,
            verticalAlignment = Alignment.CenterVertically)
        {
            Text(text = carte.valeur,
                fontSize = 16.sp,
                fontWeight = FontWeight.ExtraBold
            )
            Spacer(modifier = Modifier.width(4.dp))
            Text(text = carte.symbole,
                fontSize = 13.sp,
                color = couleurSymbole
            )
        }
    }
}



// =========================================================================
// ✅ NOUVELLE FONCTION : Reconstruire les enchères à partir du contrat
// =========================================================================

fun reconstruireEncheres(
    contrat: String,      // Ex: "4P", "3SA", "5K"
    declarant: String,    // Ex: "Nord", "Sud", "Est", "Ouest"
    donneur: String       // Ex: "N", "S", "E", "O"
): List<AnnonceJoueur> {
    val encheres = mutableListOf<AnnonceJoueur>()

    // Convertir déclarant en position courte
    val posDeclarant = when (declarant.uppercase()) {
        "NORD" -> "N"
        "SUD" -> "S"
        "EST" -> "E"
        "OUEST" -> "O"
        else -> declarant.uppercase().take(1)  // Au cas où c'est déjà "N", "S", etc.
    }

    val positions = listOf("O", "N", "E", "S")

    // Trouver l'index du donneur et du déclarant
    val indexDonneur = positions.indexOf(donneur.uppercase())
    val indexDeclarant = positions.indexOf(posDeclarant)

    if (indexDonneur == -1 || indexDeclarant == -1) {
        Log.e("TableauEncheres", "❌ Donneur ou déclarant invalide: donneur=$donneur, declarant=$posDeclarant")
        return emptyList()
    }

    // 1. Passes du donneur jusqu'au déclarant (exclu)
    var index = indexDonneur
    while (index != indexDeclarant) {
        encheres.add(AnnonceJoueur(positions[index], "Passe"))
        index = (index + 1) % 4
    }

    // 2. Le contrat par le déclarant
    encheres.add(AnnonceJoueur(posDeclarant, contrat))

    // 3. Trois passes pour clore
    index = (indexDeclarant + 1) % 4
    repeat(3) {
        encheres.add(AnnonceJoueur(positions[index], "Passe"))
        index = (index + 1) % 4
    }

    Log.i("TableauEncheres", "✅ Enchères reconstruites: ${encheres.size} annonces")
    encheres.forEach {
        Log.i("TableauEncheres", "   ${it.joueur}: ${it.annonce}")
    }

    return encheres
}

@Composable
fun TableauEncheres(
    encheres: List<AnnonceJoueur>,
    vulnerable: String,  // ✅ NOUVEAU : "NS", "EO", "Tous", "Personne"
    contrat: String = "",        // ✅ AJOUT : Contrat final (ex: "4♠", "3SA")
    declarant: String = "",      // ✅ AJOUT : Déclarant (ex: "Nord", "Sud")
    donneur: String = ""         // ✅ AJOUT : Donneur (ex: "N", "S", "E", "O")


) {
    val positionsSource = listOf("N", "E", "S", "O")
    // ✅ AJOUT : Si pas d'enchères, reconstruire à partir du contrat
    val encheresFinales = if (encheres.isEmpty() && contrat.isNotEmpty() && declarant.isNotEmpty()) {
        Log.i("AffichageDonneEcran", "TableauEncheres🔄 Reconstruction des enchères depuis contrat: $contrat par $declarant")
        reconstruireEncheres(contrat, declarant, donneur)
    } else {
        encheres
    }

    val encheresAlignees = mutableListOf<String>()

    if (encheresFinales.isNotEmpty()) {
        // 1. Détecter le premier joueur pour l'alignement
        val premierJoueur = encheresFinales.first().joueur
        val decalage = positionsSource.indexOf(premierJoueur)

        // 2. Remplir les cases vides au début
        repeat(decalage) { encheresAlignees.add("") }

        // 3. Formater les annonces
        encheresFinales.forEach { ann ->
            val texteOriginal = ann.annonce.uppercase()
            val texteFormate = when {
                texteOriginal == "PASSE" -> "Passe"
                texteOriginal == "X" || texteOriginal == "CONTRE" -> "X"
                texteOriginal == "XX" || texteOriginal == "SURCONTRE" -> "XX"
                else -> {
                    texteOriginal
                        .replace("S", " SA")
                        .replace("P", " ♠")
                        .replace("C", " ♥")
                        .replace("K", " ♦")
                        .replace("T", " ♣")
                        }
            }
            encheresAlignees.add(texteFormate)
        }
    }

    Surface(
        modifier = Modifier
            .width(220.dp)
            .heightIn(max = 200.dp),
        color = Color.White,
        shape = RoundedCornerShape(4.dp),
        border = BorderStroke(1.dp, Color.Gray)
    ) {
        Column {
            // ✅ EN-TÊTE AVEC FOND ROUGE POUR VULNÉRABLE
            Row(Modifier.fillMaxWidth()) {
                positionsSource.forEach { pos ->
                    val nom = when(pos) {
                        "O" -> "OUEST"
                        "N" -> "NORD"
                        "E" -> "EST"
                        else -> "SUD"
                    }

                    // ✅ CALCUL DU FOND SELON LA VULNÉRABILITÉ
                    val isVulnerable = when (vulnerable.uppercase()) {
                        "NS" -> pos == "N" || pos == "S"
                        "EO" -> pos == "E" || pos == "O"
                        "T" -> true
                        else -> false
                    }

                    val fondCouleur = if (isVulnerable) {
                        Color(0xFFEF5350)  // ✅ Rouge pour vulnérable
                    } else {
                        Color(0xFFECEFF1)  // Gris clair pour non vulnérable
                    }

                    val texteCouleur = if (isVulnerable) {
                        Color.White  // ✅ Texte blanc sur fond rouge
                    } else {
                        Color.Black  // Texte noir sur fond gris
                    }

                    Box(
                        modifier = Modifier
                            .weight(1f)
                            .background(fondCouleur)
                            .border(1.dp, Color(0xFF546E7A))  // ✅ AJOUT
                            .padding(vertical = 4.dp),
                        contentAlignment = Alignment.Center
                    ) {
                        Text(
                            text = nom,
                            textAlign = TextAlign.Center,
                            color = texteCouleur,
                            fontWeight = FontWeight.Bold,
                            fontSize = 10.sp
                        )
                    }
                }
            }

            // GRILLE DES ENCHÈRES (inchangée)
            LazyColumn(modifier = Modifier.fillMaxWidth()) {
                val lignes = encheresAlignees.chunked(4)
                items(lignes) { ligne ->
                    Row(Modifier.fillMaxWidth().height(IntrinsicSize.Min)) {
                        for (i in 0..3) {
                            val texte = ligne.getOrNull(i) ?: ""
                            val couleurText = when {
                                texte.contains("♥") || texte.contains("♦") -> Color.Red
                                texte == "" -> Color.Transparent
                                else -> Color.Black
                            }

                            Box(
                                modifier = Modifier
                                    .weight(1f)
                                    .fillMaxHeight()
                                    .border(1.dp, Color(0xFF546E7A))
                                    .padding(vertical = 6.dp),
                                contentAlignment = Alignment.Center
                            ) {
                                Text(
                                    text = texte,
                                    fontSize = 13.sp,
                                    fontWeight = FontWeight.ExtraBold,
                                    color = couleurText
                                )
                            }
                        }
                    }
                }
            }
        }
    }
}
