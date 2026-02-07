var div_contenu_key = null;
var div_form_validation = [];

// console.log("Début du chargement de la page...");
// const startTime = performance.now();
// console.log("Début du chargement : " + performance.timeOrigin.toFixed(2) + " ms");
// document.addEventListener("DOMContentLoaded", () => {
//   const domReady = performance.now();
//   console.log("DOM prêt après " + (domReady - startTime).toFixed(2) + " ms");
// });
// window.addEventListener("load", () => {
//   const fullLoad = performance.now();
//   console.log("Page complètement chargée après " + (fullLoad - startTime).toFixed(2) + " ms");
// });
// window.addEventListener("load", () => {
//   const perf = performance.getEntriesByType("navigation")[0];
//   console.table({
//     "Temps total": perf.duration.toFixed(2) + " ms",
//     "DOMInteractive": perf.domInteractive.toFixed(2) + " ms",
//     "DOMContentLoaded": perf.domContentLoadedEventEnd.toFixed(2) + " ms",
//     "LoadEventEnd": perf.loadEventEnd.toFixed(2) + " ms"
//   });
// });

function toggleVisibility(id) {
    const elem = document.getElementById(id);
    if (elem) {
        elem.style.display = (elem.style.display === 'block') ? 'none' : 'block';
    }
}

function clear_unhiden_element(elems) {
    elems.forEach(function(elem) {
        elem.style.display = 'none';
    });
    elems.length = 0;
}

function hiden_element(elems, elem) {
     elem.style.display = 'block';
     elems.push(elem);
}

function toggleBySelect(theSelect, IndexStr) {
    if ( theSelect.value === null || theSelect.value === '')
    {
        console.log("pas de valeur electionné");
    }
    else
    {
        console.log("Valeur : ", IndexStr);
        targetId = 'details_pb_' + IndexStr;
        //console.log("targetId : ", targetId);
        const elem = document.getElementById(targetId);
        if (elem) {
            if ( theSelect.value !== "000" ) {
                clear_unhiden_element(div_form_validation);
                hiden_element(div_form_validation, document.getElementById(targetId));
                if ( theSelect.value === "001" ) {
                    targetId = 'id_cause_' + IndexStr;
                    console.log("targetId for 001 : ", targetId);
                    const elem_1 = document.getElementById(targetId);
                    if (elem_1) {
                        hiden_element(div_form_validation, elem_1);
                    }
                    else { console.log("pas d'elem ", targetId); }
                } else if ( theSelect.value === "002" ) {
                    targetId = 'id_cause_' + IndexStr;
                    console.log("targetId for 002 : ", targetId);
                    const elem_1 = document.getElementById(targetId);
                    if (elem_1) {
                        hiden_element(div_form_validation, elem_1);
                    }
                    else { console.log("pas d'elem ", targetId); }
                } else if ( theSelect.value === "003" ) {
                    targetId = 'id_reafectation_' + IndexStr;
                    console.log("targetId for 003 : ", targetId);
                    const elem_1 = document.getElementById(targetId);
                    if (elem_1) {
                        hiden_element(div_form_validation, elem_1);
                    }
                    else { console.log("pas d'elem ", targetId); }
                } else if ( theSelect.value === "004" ) {
                    targetId = 'id_deplacement_' + IndexStr;
                    console.log("targetId for 004 : ", targetId);
                    const elem_1 = document.getElementById(targetId);
                    if (elem_1) {
                        hiden_element(div_form_validation, elem_1);
                    }
                    else { console.log("pas d'elem ", targetId); }
                } else if ( theSelect.value === "005" ) {
                    targetId = 'id_repartition_' + IndexStr;
                    console.log("targetId for 005 : ", targetId);
                    const elem_1 = document.getElementById(targetId);
                    if (elem_1) {
                        hiden_element(div_form_validation, elem_1);
                    }
                    else { console.log("pas d'elem ", targetId); }
                } else if ( theSelect.value === "006" ) {
                    targetId = 'id_change_cat_' + IndexStr;
                    console.log("targetId for 006 : ", targetId);
                    const elem_1 = document.getElementById(targetId);
                    if (elem_1) {
                        hiden_element(div_form_validation, elem_1);
                    }
                    else { console.log("pas d'elem ", targetId); }
                } else {
                    console.log("La valeur transmise par l'élément select n'est pas connue. revoir la page, la base ou ce script . Valeur transmise : ", theSelect.value );
                }
            }
            else
            {
                elem.style.display = 'none';
            }
        }
        else { console.log("Pas d'elem pour " + targetId + " , Erreur getElementById revoir la page ..."); }
    }
}

function showKey_sav(key)
{    
    if (div_contenu_key) {
        div_contenu_key.style.display = 'none';
    }

    const newDiv = document.getElementById(key);
    if (newDiv) {
        newDiv.style.display = 'block';
        div_contenu_key = newDiv;
    } 
    else {
        div_contenu_key = null;
    }
}

function showKey(cle, titre)
{
    // console.log("Entry in showkey");
    if ( div_contenu_key === null )
    {
        div_contenu_key = document.getElementById("contenu-cle");
        // console.log("Entry in getElementById");
    }
    if ( div_contenu_key != null )
    {
        div_contenu_key.innerHTML = "<h1>Chargement en cours...</h1>";
        // div_contenu_key.style.display = "block";
        div_contenu_key.style.display = "flex";
        // console.log("Valeur : ", cle);
    }
    
    fetch('Import/import_affichage_cle_comptable.php', { method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: 'cle=' + encodeURIComponent(cle) + '&titre=' + encodeURIComponent(titre) } )
        .then(response => {
            if (!response.ok) throw new Error("Erreur HTTP");
            return response.text();
            }
        )
        .then(html => {
            div_contenu_key.innerHTML = html;
            }
        )
        .catch(error => {
            div_contenu_key.innerHTML = "<p>Erreur lors du chargement des données.</p>";
            console.error("Erreur fetch : ", error);
            }
        );
    // console.log("Fin showkey");
}