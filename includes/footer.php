<script>
    // Adiciona um listener para garantir que o DOM foi completamente carregado
    document.addEventListener('DOMContentLoaded', function() {
        // Seleciona os elementos do menu admin
        const adminMenuToggle = document.getElementById('admin-menu-toggle');
        const adminSubmenu = document.getElementById('admin-submenu');
        const adminMenuItem = document.getElementById('admin-menu');

        // Verifica se o menu de administrador existe na página
        if (adminMenuToggle && adminSubmenu) {
            
            // Lógica para abrir o submenu se a página atual for uma página de admin
            const adminPages = ['cadastro_aeronaves.php', 'cadastro_pilotos.php', 'cadastro_controles.php', 'alertas.php'];
            const currentPage = window.location.pathname.split('/').pop();

            if (adminPages.includes(currentPage)) {
                adminMenuItem.classList.add('open');
                adminSubmenu.classList.add('open');
            }

            // Adiciona o evento de clique para abrir/fechar o submenu
            adminMenuToggle.addEventListener('click', function(event) {
                event.preventDefault(); // Previne que o link '#' navegue
                
                // Alterna a classe 'open' no item do menu e no submenu
                // A classe 'open' controla a visibilidade e a rotação da seta via CSS
                adminMenuItem.classList.toggle('open');
                adminSubmenu.classList.toggle('open');
            });
        }
    });
    </script>
</body>
</html>