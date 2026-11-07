@once
    <script>
        (() => {
            const expandActiveNavigationGroups = () => {
                const sidebar = document.querySelector('.fi-main-sidebar')

                if (!sidebar) {
                    return
                }

                const groupElements = Array.from(
                    sidebar.querySelectorAll('.fi-sidebar-group[data-group-label]'),
                )

                if (groupElements.length === 0) {
                    return
                }

                const allLabels = groupElements
                    .map((element) => element.dataset.groupLabel)
                    .filter(Boolean)

                const activeLabels = groupElements
                    .filter((element) => element.classList.contains('fi-active'))
                    .map((element) => element.dataset.groupLabel)
                    .filter(Boolean)

                const collapsedLabels = allLabels.filter(
                    (label) => !activeLabels.includes(label),
                )

                const store = window.Alpine?.store?.('sidebar')

                if (store && Array.isArray(store.collapsedGroups)) {
                    store.collapsedGroups = collapsedLabels
                } else {
                    localStorage.setItem(
                        'collapsedGroups',
                        JSON.stringify(collapsedLabels),
                    )
                }

                groupElements.forEach((element) => {
                    const label = element.dataset.groupLabel
                    const items = element.querySelector('.fi-sidebar-group-items')
                    const shouldCollapse = collapsedLabels.includes(label)

                    element.classList.toggle('fi-collapsed', shouldCollapse)

                    if (items && !shouldCollapse) {
                        items.style.display = ''
                    }
                })
            }

            const scheduleExpand = () => {
                requestAnimationFrame(expandActiveNavigationGroups)
            }

            document.addEventListener('DOMContentLoaded', scheduleExpand)
            document.addEventListener('livewire:navigated', scheduleExpand)
            scheduleExpand()
        })()
    </script>
@endonce
