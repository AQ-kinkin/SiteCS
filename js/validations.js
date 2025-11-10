var div_contenu_key = null;
var div_contenu_year = null;
var div_form_validation = [];


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

                console.log("targetId for 002 : ", targetId);                    const elem_1 = document.getElementById(targetId);

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



function showKey(cle, titre)

{

    console.log("Entry in showkey : " + cle + " - " + titre );



    if (div_contenu_key === null) {

        div_contenu_key = document.getElementById("validations-key");

    }



    if (div_contenu_key != null) {

        div_contenu_key.innerHTML = "<h1>Chargement en cours...</h1>";

        div_contenu_key.style.display = "flex";

    }



    let bodyParams = new URLSearchParams();

    bodyParams.append("cle", cle);

    bodyParams.append("titre", titre);

    bodyParams.append("action", "show");



    fetch('compta/load_info_validation.php', {

        method: 'POST',

        headers: {'Content-Type': 'application/x-www-form-urlencoded'},

        body: bodyParams.toString()

    })

    .then(response => {

        return response.text().then(text => {

            if (!response.ok) {

                // Si session expirée (401), rediriger vers page de déconnexion

                if (response.status === 401) {

                    try {

                        const data = JSON.parse(text);

                        if (data.redirect) {

                            window.location.href = data.redirect;

                        }

                    } catch (e) {

                        // Si pas de JSON, redirection par défaut

                        window.location.href = '/?page=Disconnect&reason=expired';

                    }

                    // Retourner une promesse rejetée pour stopper la chaîne

                    return Promise.reject('Session expirée - redirection en cours');

                }

                throw new Error("Erreur HTTP : " + response.status + " — " + text);

            }

            return text;

        });

    })

    .then(html => {

        if (!html) return; // Ne rien faire si pas de contenu

        div_contenu_key.innerHTML = html;



        // Maintenant que les formulaires sont dans le DOM, on peut les détecter

        div_contenu_key.querySelectorAll('.formfacture').forEach(form => {

            form.addEventListener('submit', event => {

                event.preventDefault();

                // Mettre à jour uniquement cette facture

                updateFacture(cle, titre, form, event.submitter ? event.submitter.name : null);

            });

        });

    })

    .catch(error => {

        // Ne pas afficher d'erreur si c'est une redirection de session

        if (error && error.toString().includes('Session expirée')) {

            console.log("Redirection vers page de déconnexion...");

            return;

        }

        div_contenu_key.innerHTML = "<p>Erreur lors du chargement des données.<br/>" + error + "</p>";

        console.error("Erreur fetch : ", error);

    });

    console.log("Fin showkey");

}



function updateFacture(cle, titre, form, buttonname = null)
{
    console.log("Entry in updateFacture");

    const formdata = new FormData(form);
    const id_line = formdata.get('id_line');

    if (!id_line) {

        console.error("updateFacture :: id_line manquant");
        return;
    }

    let bodyParams = new URLSearchParams();

    bodyParams.append("cle", cle);

    bodyParams.append("titre", titre);

    

    // Déterminer l'action selon le bouton cliqué

    if (buttonname === 'reopen') {

        bodyParams.append("action", "reopen");

    } else {

        bodyParams.append("action", "update");

    }

    

    // Ajouter toutes les données du formulaire

    for (let [key, value] of formdata.entries()) {

        bodyParams.append(key, value);

    }

    

    fetch('compta/load_info_validation.php', {

        method: 'POST',

        headers: {'Content-Type': 'application/x-www-form-urlencoded'},

        body: bodyParams.toString()

    })

    .then(response => {

        return response.text().then(text => {

            if (!response.ok) {

                // Si session expirée (401), rediriger vers page de déconnexion

                if (response.status === 401) {

                    try {

                        const data = JSON.parse(text);

                        if (data.redirect) {

                            window.location.href = data.redirect;

                        }

                    } catch (e) {

                        // Si pas de JSON, redirection par défaut

                        window.location.href = '/?page=Disconnect&reason=expired';

                    }

                    // Retourner une promesse rejetée pour stopper la chaîne

                    return Promise.reject('Session expirée - redirection en cours');

                }

                throw new Error("Erreur HTTP : " + response.status + " — " + text);

            }

            return text;

        });

    })

    .then(html => {

        if (!html) return; // Ne rien faire si pas de contenu

        

        // Remplacer uniquement la div de cette facture

        const factureDiv = document.getElementById("facture_" + id_line);

        if (factureDiv) {

            factureDiv.outerHTML = html;

            console.log("Facture " + id_line + " mise à jour");

            

            // Réattacher le listener au nouveau formulaire dans le nouveau HTML

            const newFactureDiv = document.getElementById("facture_" + id_line);

            if (newFactureDiv) {

                const newForm = newFactureDiv.querySelector('.formfacture');

                if (newForm) {

                    newForm.addEventListener('submit', event => {

                        event.preventDefault();

                        updateFacture(cle, titre, newForm, event.submitter ? event.submitter.name : null);

                    });

                    console.log("Listener réattaché au formulaire facture_" + id_line);

                }

            }

        } else {

            console.error("Div facture_" + id_line + " non trouvée");

        }

    })

    .catch(error => {

        // Ne pas afficher d'erreur si c'est une redirection de session

        if (error && error.toString().includes('Session expirée')) {

            console.log("Redirection vers page de déconnexion...");

            return;

        }

        console.error("Erreur fetch updateFacture : ", error);

        alert("Erreur lors de la mise à jour de la facture : " + error);

    });

    

    console.log("Fin updateFacture");

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

            return response.text().then(text => {

                if (!response.ok) {

                    // Si session expirée (401), rediriger vers page de déconnexion

                    if (response.status === 401) {

                        try {

                            const data = JSON.parse(text);

                            if (data.redirect) {

                                window.location.href = data.redirect;

                            }

                        } catch (e) {

                            window.location.href = '/?page=Disconnect&reason=expired';

                        }

                        // Retourner une promesse rejetée pour stopper la chaîne

                        return Promise.reject('Session expirée - redirection en cours');

                    }

                    throw new Error("Erreur HTTP : " + response.status);

                }

                return text;

            });

        }

        )

        .then(html => {

            if (!html) return; // Ne rien faire si pas de contenu

            div_contenu_key.innerHTML = html;

            }

        )

        .catch(error => {

            // Ne pas afficher d'erreur si c'est une redirection de session

            if (error && error.toString().includes('Session expirée')) {

                console.log("Redirection vers page de déconnexion...");

                return;

            }

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

// Fonction pour basculer entre l'affichage et l'édition de la pièce jointe
function toggleURLEdit(idLine) {
    // Masquer l'affichage du lien
    const display = document.getElementById('url_display_' + idLine);
    if (display) {
        display.style.display = 'none';
    }
    
    // Afficher le champ d'édition
    const edit = document.getElementById('url_edit_' + idLine);
    if (edit) {
        edit.style.display = 'block';
    }
}

