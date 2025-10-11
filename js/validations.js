var div_contenu_key = null;
var div_contenu_year = null;
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

document.addEventListener("DOMContentLoaded", () => {
    div_contenu_key = document.getElementById("validations-key");
    div_contenu_year = document.getElementById("periode");
});

function toggleVisibility(id) {
    console.log("Start toggleVisibility", id);
    const elem = document.getElementById(id);
    if (elem) {
        elem.style.display = (elem.style.display === 'block') ? 'none' : 'block';
        console.log("toggleVisibility :: element found");
    } else {
        console.log("toggleVisibility :: element not found for id: ", id);
    }
    console.log("End toggleVisibility");
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
    console.log("toggleBySelect :: Entry ...", IndexStr);
    if ( theSelect.value === null || theSelect.value === '')
    {
        console.log("pas de valeur electionné");
    }
    else
    {
        console.log("Valeur : ", IndexStr);
        targetId = 'details_pb_' + IndexStr;
        console.log("targetId : ", targetId);
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

// function showKey_sav(key)
// {    
//     if (div_contenu_key) {
//         div_contenu_key.style.display = 'none';
//     }

//     const newDiv = document.getElementById(key);
//     if (newDiv) {
//         newDiv.style.display = 'block';
//         div_contenu_key = newDiv;
//     } 
//     else {
//         div_contenu_key = null;
//     }
// }

// function showKey(cle, titre)
// {
//     console.log("Entry in showkey : " + cle + " - " + titre );
//     if ( div_contenu_key === null )
//     {
//         div_contenu_key = document.getElementById("validations-key");
//         console.log("Entry in getElementById : ", div_contenu_key);
//     }
//     if ( div_contenu_key != null )
//     {
//         console.log("Entry with div_contenu_key not null");
//         div_contenu_key.innerHTML = "<h1>Chargement en cours...</h1>";
//         // div_contenu_key.style.display = "block";
//         div_contenu_key.style.display = "flex";
//         console.log("Valeur : ", cle);
//     }
    
//     fetch('compta/load_info_validation.php', { method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: 'action=show' + '&cle=' + encodeURIComponent(cle) + '&titre=' + encodeURIComponent(titre) } )
//     .then(response => {
//         return response.text().then(text => {
//             if (!response.ok) {
//                 throw new Error("Erreur HTTP : " + response.status + " — " + text);
//             }
//             return text;
//         });
//     })
//     .then(html => {
//         div_contenu_key.innerHTML = html;
//         div_contenu_key.scrollIntoView({ behavior: 'smooth', block: 'start' });
//         })
//         .catch(error => {
//             div_contenu_key.innerHTML = "<p>Erreur lors du chargement des données.<br/>" + error + "</p>";
//             console.error("Erreur fetch : ", error);
//             }
//         );
//     console.log("Fin showkey");
// }

function showKey(cle, titre, callingform = null, buttonname = null)
{
    let id_line = null;
    console.log("Entry in showkey : " + cle + " - " + titre );

    if (div_contenu_key === null) {
        div_contenu_key = document.getElementById("validations-key");
        // console.log("Entry in getElementById : ", div_contenu_key);
    }

    if (div_contenu_key != null) {
        // console.log("Entry with div_contenu_key not null");
        div_contenu_key.innerHTML = "<h1>Chargement en cours...</h1>";
        div_contenu_key.style.display = "flex";
        // console.log("Valeur : ", cle);
    }

    let bodyParams = new URLSearchParams();
    bodyParams.append("cle", cle);
    bodyParams.append("titre", titre);

    if ( callingform != null ) {
        const formdata = new FormData(callingform);
        console.log("Valeurs : ", formdata);
        if ( buttonname != null ) {
            console.log("Valeur name button : ", buttonname);
            bodyParams.append("action", buttonname);
            if ( buttonname === 'reopen' ) {
                id_line = formdata.get('id_line');
                console.log("Valeur id_ligne : ", id_line);
            }
        } else {
            bodyParams.append("action", "update");
        }
        for (let [key, value] of formdata.entries()) {
            bodyParams.append(key, value);
        }
    }
    else
    {
        bodyParams.append("action", "show");
    }

    fetch('compta/load_info_validation.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: bodyParams.toString()
    })
    .then(response => {
        return response.text().then(text => {
            if (!response.ok) {
                throw new Error("Erreur HTTP : " + response.status + " — " + text);
            }
            return text;
        });
    })
    .then(html => {
        div_contenu_key.innerHTML = html;

        // Maintenant que les formulaires sont dans le DOM, on peut les détecter
        div_contenu_key.querySelectorAll('.formfacture').forEach(form => {
            // if (!form.dataset.ajaxified) {
                // form.dataset.ajaxified = "true"; // Marque comme traité
                form.addEventListener('submit', event => {
                    event.preventDefault();
                    if (event.submitter) {
                        showKey(cle, titre, form, event.submitter.name);
                    } else {
                        showKey(cle, titre, form);
                    }
                });
            // }
            
        });

        if ( id_line != null ) {
            const theSelect = document.getElementById("id_statut_" + id_line);
            toggleBySelect(theSelect, id_line); 
        }
        
        if ( callingform != null ) {
            toggleVisibility(callingform.parentElement.parentElement.id);
            callingform.focus();
            // callingform.scrollIntoView({ behavior: 'smooth', block: 'center' });
            setTimeout(() => {
                // callingform.scrollIntoView({ behavior: 'instant', block: 'nearest' });
                scrollFormIntoView(callingform);
            }, 100);
        }

    })
    .catch(error => {
        div_contenu_key.innerHTML = "<p>Erreur lors du chargement des données.<br/>" + error + "</p>";
        console.error("Erreur fetch : ", error);
    });
    console.log("Fin showkey");
}

function updateYear()
{
    if ( div_contenu_key === null )
    {
        div_contenu_key = document.getElementById("validations-key");
        console.log("updateYear :: Entry in getElementById : ", div_contenu_key);
    }
    if ( div_contenu_year === null )
    {
        div_contenu_year = document.getElementById("periode");
        console.log("updateYear :: Entry in getElementById : ", div_contenu_year);
    }
    
    if ( div_contenu_year == null || div_contenu_key == null )
    {
        console.log("updateYear :: Entry with div_contenu_key null");
        return;
    }
    
    console.log("updateYear :: Call load_info_validation.php. valeur =",  div_contenu_year.value);
    fetch('compta/load_info_validation.php', { method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: 'action=keylst' + '&periode=' + encodeURIComponent(div_contenu_year.value) } )
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
    console.log("End updateYear");
}

function scrollFormIntoView(formElement) {
        
    console.log("scrollFormIntoView :: Entry in getElementById : ", div_contenu_key);

    const container = document.querySelector('.content');
    if (!container) {
        console.warn('Conteneur scrollable .content introuvable.');
        return;
    }

    // Position du formulaire par rapport au conteneur
    const formRect = formElement.getBoundingClientRect();
    const containerRect = container.getBoundingClientRect();

    const offset = formRect.top - containerRect.top;

    container.scrollTo({
        top: container.scrollTop + offset - 50, // -50 pour un peu de marge en haut
        behavior: 'smooth'
    });
}
