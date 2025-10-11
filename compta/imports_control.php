<?php
// Simule les conflits provenant de la BDD
$conflits = [
  [
    'id_conflit' => 1,
    'gauche' => [
      ['index1' => 'g1', 'label' => 'Chien'],
      ['index1' => 'g2', 'label' => 'Chat'],
    ],
    'droite' => [
      ['index2' => 'd1', 'label' => 'Dog'],
      ['index2' => 'd2', 'label' => 'Cat'],
    ]
  ],
  [
    'id_conflit' => 2,
    'gauche' => [
      ['index1' => 'g3', 'label' => 'Maison'],
    ],
    'droite' => [
      ['index2' => 'd3', 'label' => 'House'],
      ['index2' => 'd4', 'label' => 'Building'],
    ]
  ]
];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Test Conflits</title>
  <style>
    body {
      font-family: sans-serif;
    }

    .conflit {
      border: 1px solid #ccc;
      padding: 10px;
      margin: 30px auto;
      max-width: 800px;
      position: relative;
      display: block; /* S'assurer que chaque conflit est sur une ligne */
    }

    .zone {
      display: flex;
      justify-content: space-between;
      position: relative;
    }

    .col {
      width: 45%;
    }

    .item {
      background: #f0f0f0;
      padding: 8px;
      margin: 5px 0;
      border-radius: 5px;
      border: 1px solid #ccc;
      cursor: pointer;
      position: relative;
    }

    .item.selected {
      background: #b3d9ff;
    }

    svg {
      position: absolute;
      top: 0;
      left: 0;
      pointer-events: none;
      width: 100%;
      height: 100%;
      z-index: 0;
    }

    .actions {
      margin-top: 10px;
    }

    button {
      border-radius: 5px;
      margin-right: 30px;
      padding-top: 2px;
      padding-bottom: 2px;
      padding-right: 5px;
      padding-left: 5px;
    }

    .resolved {
      background: #e6ffe6;
      padding: 10px;
      text-align: center;
      font-weight: bold;
    }
  </style>
</head>
<body>
  <h2 style="text-align:center;">Interface de résolution des conflits</h2>

  <?php foreach ($conflits as $conflit): ?>
    <div class="conflit" id="conflit-<?= $conflit['id_conflit'] ?>" data-id="<?= $conflit['id_conflit'] ?>">
      <div class="zone">
        <div class="col gauche">
          <?php foreach ($conflit['gauche'] as $g): ?>
            <div class="item" data-side="left" data-index="<?= $g['index1'] ?>"><?= htmlspecialchars($g['label']) ?></div>
          <?php endforeach; ?>
        </div>

        <svg></svg>

        <div class="col droite">
          <?php foreach ($conflit['droite'] as $d): ?>
            <div class="item" data-side="right" data-index="<?= $d['index2'] ?>"><?= htmlspecialchars($d['label']) ?></div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="actions">
        <button class="valider-btn">Valider</button>
        <button class="cancel-btn">Annuler</button>
      </div>
    </div>
  <?php endforeach; ?>

  <script>
    document.querySelectorAll('.conflit').forEach(conflit => {
      let selectedLeft = null;
      const svg = conflit.querySelector('svg');
      const associations = []; // Tableau des paires

      function drawLines() {
        svg.innerHTML = '';
        const containerRect = conflit.getBoundingClientRect();

        associations.forEach(assoc => {
          const left = conflit.querySelector(`.item[data-index="${assoc.left}"]`);
          const right = conflit.querySelector(`.item[data-index="${assoc.right}"]`);

          if (!left || !right) return;

          const rect1 = left.getBoundingClientRect();
          const rect2 = right.getBoundingClientRect();

          const x1 = rect1.right - containerRect.left;
          const y1 = rect1.top + rect1.height / 2 - containerRect.top;

          const x2 = rect2.left - containerRect.left;
          const y2 = rect2.top + rect2.height / 2 - containerRect.top;

          const line = document.createElementNS("http://www.w3.org/2000/svg", "line");
          line.setAttribute("x1", x1);
          line.setAttribute("y1", y1);
          line.setAttribute("x2", x2);
          line.setAttribute("y2", y2);
          line.setAttribute("stroke", "blue");
          line.setAttribute("stroke-width", "2");
          svg.appendChild(line);
        });
      }

      function clearAll() {
        selectedLeft = null;
        svg.innerHTML = '';
        associations.length = 0;
        conflit.querySelectorAll('.item').forEach(i => i.classList.remove('selected'));
      }

      conflit.querySelectorAll('.item').forEach(item => {
        item.addEventListener('click', () => {
          const side = item.dataset.side;

          if (side === 'left') {
            selectedLeft = item;
            //conflit.querySelectorAll('.item').forEach(i => i.classList.remove('selected'));
            item.classList.add('selected');
          } else if (side === 'right' && selectedLeft) {
            const pair = {
              left: selectedLeft.dataset.index,
              right: item.dataset.index
            };

            // Empêcher doublons exacts
            const exists = associations.find(a => a.left === pair.left && a.right === pair.right);
            if (!exists) {
              associations.push(pair);
              // selectedLeft.classList.remove('selected');
              selectedLeft = null;
              drawLines();
              item.classList.add('selected');
            }
          }
        });
      });

      // Cancel (efface uniquement dans la division en cours)
      conflit.querySelector('.cancel-btn').addEventListener('click', () => {
        clearAll();
      });

      // Valider (AJAX envoie toutes les associations)
      conflit.querySelector('.valider-btn').addEventListener('click', () => {
        const id_conflit = conflit.dataset.id;

        if (associations.length === 0) {
          alert("Aucune association à valider !");
          return;
        }

        fetch('valider.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ id_conflit, associations })
        })
        .then(res => res.json())
        .then(data => {
          if (data.success) {
            conflit.innerHTML = `<div class="resolved">✅ Conflit #${id_conflit} résolu</div>`;
          } else {
            alert("Erreur de validation");
          }
        })
        .catch(() => alert("Erreur AJAX"));
      });
    });
  </script>
</body>
</html>