class ConflitHandler {

   constructor(element) {

       this.element = element;

       //this.myzone = element.closest('.zone');

       this.myzone = element.querySelector('.zone');

       this.selectedLeft = null;

       this.svg = element.querySelector('svg');

       this.associations = [];

       

       this.init();

   }

   

   init() {

       this.setupEventListeners();

   }

   

   drawLines() {

       

        console.log('drawLines Entry');



       this.svg.innerHTML = '';

       //const myzone = this.element.closest('.zone');

       const containerRect = this.myzone.getBoundingClientRect();

       

       console.log("Rect de ref:", `(${containerRect.right},${containerRect.top},${containerRect.left},${containerRect.bottom})`);

       console.log("Associations:", this.associations);



       this.associations.forEach(assoc => {

        

           console.log("Processing assoc:", assoc);



           console.log("Container element:", this.element);

           console.log("Type element:", this.element.tagName);

           console.log("Classes element:", this.element.className);



           console.log("Container myzone:", this.myzone);

           console.log("Type myzone:", this.myzone.tagName);

           console.log("Classes myzone:", this.myzone.className);



           const left = this.element.querySelector(`.item[data-index="${assoc.left}"]`);

           const right = this.element.querySelector(`.item[data-index="${assoc.right}"]`);

           if (!left || !right) return;

           

           console.log("Left:", left ? left.className : "not found");

           console.log("Right:", right ? right.className : "not found");



           const rect1 = left.getBoundingClientRect();

           const rect2 = right.getBoundingClientRect();



           console.log("Line from", left.className, "→", right.className,`(${rect1.right},${rect1.top},${rect1.left},${rect1.bottom}) → (${rect2.right},${rect2.top},${rect2.left},${rect2.bottom})`);



           const x1 = Math.round( rect1.right - containerRect.left );

           const y1 = Math.round( rect1.top + rect1.height / 2 - containerRect.top );

           const x2 = Math.round( rect2.left - containerRect.left );

           const y2 = Math.round( rect2.top + rect2.height / 2 - containerRect.top );

           

           const line = document.createElementNS("http://www.w3.org/2000/svg", "line");

           line.setAttribute("x1", x1);

           line.setAttribute("y1", y1);

           line.setAttribute("x2", x2);

           line.setAttribute("y2", y2);

           line.setAttribute("stroke", "blue");

           line.setAttribute("stroke-width", "2");

           this.svg.appendChild(line);

       });

   }

   

   clearAll() {
       console.log("Entry in clearAll");
       this.selectedLeft = null;
       this.svg.innerHTML = '';
       this.associations.length = 0;
       this.element.querySelectorAll('.item').forEach(i => i.classList.remove('selected'));
   }

    handleForced() {
        console.log("Entry in handleForced");
        const id_conflit = this.element.dataset.id;
        
        const itemsGauche = this.element.querySelectorAll('.col.gauche .item');
        const itemsDroite = this.element.querySelectorAll('.col.droite .item');
    
        if (itemsDroite.length > 0) {
            alert("La colonne droite n'est pas vide ! Utilisez le bouton Valider.");
            return;
        }
        
        if (itemsGauche.length === 0) {
            alert("Aucune ligne à forcer !");
            return;
        }

        //if (!confirm("Êtes-vous sûr de vouloir forcer cette ligne ?")) {
        //    return;
        //}

        const form = document.getElementById('form_imports');
        const step = form ? form.querySelector('input[name="step"]').value : '';

        const formData = new FormData();
        formData.append('form_num', 11);
        formData.append('action', 'forced');
        formData.append('step', step);
        
        var count=0;
            itemsGauche.forEach(item => {
            const index = item.dataset.index;
            if (index) {
                formData.append('index' + count++, index);
            }
        });

        fetch("compta/import_excel.php", {
            method: "POST",
            body: formData
        })
        .then(response => { 
            if (!response.ok) throw new Error("Erreur HTTP");
            return response.text();
        })
        .then(html => {
            this.element.innerHTML = html;
        })
        .catch((err) => {
            const mess = document.getElementById("imports-message");
            if (mess) mess.innerHTML = "<p style='color:red;'>Erreur : " + err + "</p>";
        });
        
        console.log("Exit of handleForced");
    } 

   setupEventListeners() {

       // Items click
       this.element.querySelectorAll('.item').forEach(item => {
           item.addEventListener('click', () => this.handleItemClick(item));
       });

       // Cancel button
       this.element.querySelector('.clear-btn').addEventListener('click', (e) => {
           e.preventDefault();
           this.clearAll();
       });

       // Valider button - retourne une Promise
       this.element.querySelector('.valider-btn').addEventListener('click', (e) => {
           e.preventDefault();
           this.handleValidation();
       });

       const forcedBtn = this.element.querySelector('.forced-btn');
        if (forcedBtn) {
            forcedBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.handleForced();
            });
        } else {
            console.log('⚠️ Bouton forced-btn non trouvé !');
        }

   }


   handleItemClick(item) {

       const side = item.dataset.side;

       

       if (side === 'left') {

           this.selectedLeft = item;

           item.classList.add('selected');

       } else if (side === 'right' && this.selectedLeft) {

           const pair = {

               left: this.selectedLeft.dataset.index,

               right: item.dataset.index

           };

           

           const exists = this.associations.find(a => 

               a.left === pair.left && a.right === pair.right

           );

           

           if (!exists) {

               this.associations.push(pair);

               this.selectedLeft = null;

               this.drawLines();

               item.classList.add('selected');

           }

       }

   }

   
   handleValidation() {

       console.log("Entry in handleValidation");
       const id_conflit = this.element.dataset.id;

       if (this.associations.length === 0) {
           alert("Aucune association à valider !");
           return;
       }
       
       const form = document.getElementById('form_imports');
       const step = form ? form.querySelector('input[name="step"]').value : '';

       console.log("association : ", this.associations);
       const formData = new FormData();

       formData.append('form_num', 10);
       formData.append('step', step);
       // Identifiant du bloc (utile côté PHP pour router/diagnostiquer)
       formData.append('id_conflit', id_conflit);

       var count=0;
       this.associations.forEach( line => {
         formData.append('data'+count++, 'left:' + line.left + ',right:' + line.right);
       });


       fetch("compta/import_excel.php", {
           method: "POST",
           body: formData
        })
        .then( response => { 
            if (!response.ok) throw new Error("Erreur HTTP");
            return response.text();
        })
        .then( html => {
            this.element.innerHTML = html;
        })
        .catch((err) => {
            if (mess) mess.innerHTML = "<p style='color:red;'>Erreur : " + err + "</p>";
        });

       console.log("Exit of handleValidation");
   }

   
   // Méthode pour marquer comme résolu
   markAsResolved(id_conflit) {

       this.element.innerHTML = `<div class="resolved">✅ Conflit #${id_conflit} résolu</div>`;

   }

}



function gestion_submit_form(e) {

    

    // *****************************

    // Empêche le rechargement

    e.preventDefault(); 

    // *****************************
    console.log("========================================");
    console.log("🔵 DÉBUT gestion_submit_form");
    console.log("========================================");
    // console.log("entry gestion_submit_form 2 -------"); 

    const form = e.target;
    const data = new FormData(form);
    const boxinfo = document.getElementById("imports-box");
    const mess = document.getElementById("imports-message");

    if (boxinfo) {
        let section = '\t\t<div id="loader" class="loader-container">';
        section += '\t\t\t<div class="spinner"></div>';
        section += '\t\t\t<div class="loader-message">Traitement en cours...</div>';
        section += '\t\t</div>';
        boxinfo.innerHTML = section;
        //console.log('imports-message found');
        console.log('✅ Loader affiché');
    } else {
        // console.log('imports-message not found for Étape 2')
        console.log('❌ imports-box not found');
    }

    

    // Lecture des valeurs contenu dans le formulaire

    console.log("form_imports submi : ");
    for (let pair of data.entries()) {
        console.log(pair[0] + ': ' + pair[1]);
    }

    if ( !data.has("form_num") ) {
        console.log("❌ ERREUR : form_num manquant");
        mess.innerHTML = "<p style='color:red;'>Erreur : manque le num du formulaire</p>";
        return false;
    }

    const value = data.get("form_num").trim();
    if ( value === "" ) {
        console.log("❌ ERREUR : form_num vide");
        mess.innerHTML = "<p style='color:red;'>Erreur : Le num du formulaire est manquant</p>";
        return false;
    }

     

    // Envoi des données avec fetch vers le traitement PHP
    console.log("🚀 Lancement fetch vers import_excel.php");
    fetch("compta/import_excel.php", {
        method: "POST",
        body: data
    })
    .then(response => { 
        console.log("📥 Réponse reçue, status:", response.status);
        if (!response.ok) throw new Error("Erreur HTTP");
        return response.text();
        }
    )
    .then(html => {
        console.log("📄 HTML reçu, longueur:", html.length);
        console.log("📄 Début du HTML:", html.substring(0, 200));

        if (boxinfo) {
            console.log("✅ Mise à jour de imports-box");
            boxinfo.innerHTML = html;
            
            console.log("🔍 Recherche des .conflit...");
            const nouveauxConflits = document.querySelectorAll('.conflit');
            console.log("📊 Nombre de conflits trouvés:", nouveauxConflits.length);

            if (nouveauxConflits.length > 0) {
                console.log("🎯 Initialisation des ConflitHandler:");
                nouveauxConflits.forEach( (conflit, index) => {
                    console.log("  - Conflit", index + 1, ":", conflit);
                    new ConflitHandler(conflit);
                    console.log("  ✅ ConflitHandler initialisé pour conflit", index + 1);
                });
            } else {
                console.log("⚠️ Aucun conflit à initialiser");
            }

            console.log("🔗 Recherche du formulaire pour réattacher l'event listener...");
            const newForm = document.getElementById('form_imports');
            if (newForm) {
                console.log("✅ Formulaire trouvé, réattachement de l'event listener");
                newForm.removeEventListener('submit', gestion_submit_form);
                newForm.addEventListener('submit', gestion_submit_form);
                console.log("✅ Event listener réattaché !");
            } else {
                console.log("❌ ERREUR : Formulaire form_imports non trouvé !");
            }
        }
        else {
            console.log('❌ imports-box not found après fetch');
        }

        console.log("========================================");
        console.log("🟢 FIN gestion_submit_form");
        console.log("========================================");
    })
    .catch((err) => {
        console.log("========================================");
        console.log("🔴 ERREUR dans fetch:", err);
        console.log("========================================");
        if (mess) mess.innerHTML = "<p style='color:red;'>Erreur : " + err + "</p>";
        // if (!mess) console.log('imports-message not found for erreur message');
    });

}



document.addEventListener('DOMContentLoaded', function () {

    const form = document.getElementById('form_imports');
    if (form) {
        form.addEventListener('submit', gestion_submit_form );
        console.log('DOMContentLoaded form_imports is load');
    }

});





window.onload = function () {

    // const btn = document.getElementById('form_imports');

    // if (btn) {

    //     // btn.addEventListener('click', function () {

    //     console.log('window.onload form_imports is load');

    //     // });

    // } else {

    //     console.log('window.onload form_imports is not exists');

    // }



    // const arraytest = document.querySelectorAll('.conflit');    

    // if (arraytest.length) {

    //     console.log('Traitement des éléments. nombre : ' + arraytest.length + "");

    // } else {

    //     console.log('Aucun élément à traiter');

    // }



    // arraytest.forEach((conflit, index) => {

    //     console.log(`Élément ${index}:`, conflit);

    // });

    // document.querySelectorAll('.conflit').forEach(conflit => {

    //     let selectedLeft = null;

    //     const svg = conflit.querySelector('svg');

    //     const associations = []; // Tableau des paires



    //     console.log('querySelectorAll Entry ...');



    //     function drawLines() {

    //         svg.innerHTML = '';

    //         const containerRect = conflit.getBoundingClientRect();



    //         associations.forEach(assoc => {

    //         const left = conflit.querySelector(`.item[data-index="${assoc.left}"]`);

    //         const right = conflit.querySelector(`.item[data-index="${assoc.right}"]`);



    //         if (!left || !right) return;



    //         const rect1 = left.getBoundingClientRect();

    //         const rect2 = right.getBoundingClientRect();



    //         const x1 = rect1.right - containerRect.left;

    //         const y1 = rect1.top + rect1.height / 2 - containerRect.top;



    //         const x2 = rect2.left - containerRect.left;

    //         const y2 = rect2.top + rect2.height / 2 - containerRect.top;



    //         const line = document.createElementNS("http://www.w3.org/2000/svg", "line");

    //         line.setAttribute("x1", x1);

    //         line.setAttribute("y1", y1);

    //         line.setAttribute("x2", x2);

    //         line.setAttribute("y2", y2);

    //         line.setAttribute("stroke", "blue");

    //         line.setAttribute("stroke-width", "2");

    //         svg.appendChild(line);

    //         });

    //     }



    //     function clearAll() {

    //         selectedLeft = null;

    //         svg.innerHTML = '';

    //         associations.length = 0;

    //         conflit.querySelectorAll('.item').forEach(i => i.classList.remove('selected'));

    //     }



    //     conflit.querySelectorAll('.item').forEach(item => {

    //         item.addEventListener('click', () => {

    //         const side = item.dataset.side;



    //         if (side === 'left') {

    //             selectedLeft = item;

    //             //conflit.querySelectorAll('.item').forEach(i => i.classList.remove('selected'));

    //             item.classList.add('selected');

    //         } else if (side === 'right' && selectedLeft) {

    //             const pair = {

    //             left: selectedLeft.dataset.index,

    //             right: item.dataset.index

    //             };



    //             // Empêcher doublons exacts

    //             const exists = associations.find(a => a.left === pair.left && a.right === pair.right);

    //             if (!exists) {

    //             associations.push(pair);

    //             // selectedLeft.classList.remove('selected');

    //             selectedLeft = null;

    //             drawLines();

    //             item.classList.add('selected');

    //             }

    //         }

    //         });

    //     });



    //     // Cancel (efface uniquement dans la division en cours)

    //     conflit.querySelector('.cancel-btn').addEventListener('click', () => {

    //         clearAll();

    //     });



    //     // Valider (AJAX envoie toutes les associations)

    //     conflit.querySelector('.valider-btn').addEventListener('click', () => {

    //         const id_conflit = conflit.dataset.id;



    //         if (associations.length === 0) {

    //         alert("Aucune association à valider !");

    //         return;

    //         }



    //         fetch('valider.php', {

    //         method: 'POST',

    //         headers: { 'Content-Type': 'application/json' },

    //         body: JSON.stringify({ id_conflit, associations })

    //         })

    //         .then(res => res.json())

    //         .then(data => {

    //         if (data.success) {

    //             conflit.innerHTML = `<div class="resolved">✅ Conflit #${id_conflit} résolu</div>`;

    //         } else {

    //             alert("Erreur de validation");

    //         }

    //         })

    //         .catch(() => alert("Erreur AJAX"));

    //     });

    // });

};



