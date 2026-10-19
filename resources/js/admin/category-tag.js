function normalizeVietnameseText(value = '') {
    return value
        .toLocaleLowerCase('vi-VN')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/đ/g, 'd')
        .trim();
}

function slugifyVietnamese(value = '') {
    return value
        .replace(/đ/g, 'd')
        .replace(/Đ/g, 'D')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

document.addEventListener('DOMContentLoaded', () => {
    /*
    * Script này được load chung qua app.js.
    * Nếu không phải trang Categories & Tags thì dừng luôn.
    */
    const taxonomyManager = document.getElementById('taxonomyManager');

    const showConfirmModal = window.showConfirmModal;

    if (!taxonomyManager) {
        return;
    }
    // =====================================================
    // TAB SWITCHING
    // =====================================================

    const categoryTabButton =
        document.getElementById('categoryTabButton');

    const tagTabButton =
        document.getElementById('tagTabButton');

    const categoriesTab =
        document.getElementById('categoriesTab');

    const tagsTab =
        document.getElementById('tagsTab');



    function setActiveTab(activeButton, inactiveButton) {
        activeButton.classList.add(
            'border-b-2',
            'border-[#80d4ff]',
            'font-semibold',
            'text-[#80d4ff]'
        );

        activeButton.classList.remove(
            'text-[#87929a]'
        );


        inactiveButton.classList.remove(
            'border-b-2',
            'border-[#80d4ff]',
            'font-semibold',
            'text-[#80d4ff]'
        );

        inactiveButton.classList.add(
            'text-[#87929a]'
        );
    }


    function showCategoryTab() {
        categoriesTab.classList.remove('hidden');
        tagsTab.classList.add('hidden');


        setActiveTab(
            categoryTabButton,
            tagTabButton
        );
    }


    function showTagTab() {
        tagsTab.classList.remove('hidden');
        categoriesTab.classList.add('hidden');

        setActiveTab(
            tagTabButton,
            categoryTabButton
        );
    }


    categoryTabButton?.addEventListener(
        'click',
        showCategoryTab
    );


    tagTabButton?.addEventListener(
        'click',
        showTagTab
    );
    // =====================================================
    // RESTORE ACTIVE TAB AFTER RELOAD
    // =====================================================

    const activeTab =
        sessionStorage.getItem(
            'taxonomyActiveTab'
        );


    if (activeTab === 'tags') {
        showTagTab();

        sessionStorage.removeItem(
            'taxonomyActiveTab'
        );
    }

    // =====================================================
    // CATEGORY MANAGER
    // =====================================================

    const categoryManager =
        document.getElementById('categoryManager');

    if (!categoryManager) {
        return;
    }


    const categoryStoreUrl =
        categoryManager.dataset.storeUrl;

    const csrfToken =
        categoryManager.dataset.csrfToken;


    // =====================================================
    // CATEGORY SEARCH + FILTER
    // =====================================================

    const searchInput =
        document.getElementById('categorySearch');

    const statusFilter =
        document.getElementById('statusFilter');

    const categoryRows =
        document.querySelectorAll('.category-row');


    function filterCategories() {
        const keyword =
            normalizeVietnameseText(searchInput?.value ?? '');

        const status =
            statusFilter?.value ?? 'ALL';

        categoryRows.forEach((row) => {
            const name =
                normalizeVietnameseText(row.dataset.name ?? '');

            const slug =
                normalizeVietnameseText(row.dataset.slug ?? '');

            const rowStatus =
                row.dataset.status ?? '';

            const matchesKeyword =
                name.includes(keyword) ||
                slug.includes(keyword);

            const matchesStatus =
                status === 'ALL' ||
                rowStatus === status;

            row.classList.toggle(
                'hidden',
                !(matchesKeyword && matchesStatus)
            );
        });
    }


    searchInput?.addEventListener(
        'input',
        filterCategories
    );


    statusFilter?.addEventListener(
        'change',
        filterCategories
    );


    // =====================================================
    // CATEGORY FORM ELEMENTS
    // =====================================================

    const categoryForm =
        document.getElementById('categoryForm');

    const errorBox =
        document.getElementById('categoryFormError');

    const saveButton =
        document.getElementById('saveCategoryButton');

    const saveButtonText =
        document.getElementById('saveCategoryButtonText');

    const editorTitle =
        document.getElementById('categoryEditorTitle');

    const cancelEditButton =
        document.getElementById('cancelCategoryEdit');


    const nameInput =
        document.getElementById('categoryName');

    const slugInput =
        document.getElementById('categorySlug');

    const parentInput =
        document.getElementById('categoryParent');

    const descriptionInput =
        document.getElementById('categoryDescription');

    const statusInput =
        document.getElementById('categoryStatus');
    
    const colorInput =
        document.getElementById('categoryColor');

    const iconInput =
        document.getElementById('categoryIcon');


    let editingUrl = null;

    let slugWasEdited = false;

    function resetParentOptions() {
        if (!parentInput) {
            return;
        }

        Array.from(parentInput.options)
            .forEach((option) => {
                option.disabled = false;
            });
    }


    function getDescendantIds(categoryId) {
        if (!parentInput) {
            return new Set();
        }

        const options =
            Array.from(parentInput.options);

        const descendants =
            new Set();

        let changed = true;

        while (changed) {
            changed = false;

            options.forEach((option) => {
                if (!option.value) {
                    return;
                }

                const parentId =
                    option.dataset.parentId ?? '';

                const isDirectChild =
                    parentId === categoryId;

                const isChildOfDescendant =
                    descendants.has(parentId);

                if (
                    (isDirectChild || isChildOfDescendant) &&
                    !descendants.has(option.value)
                ) {
                    descendants.add(
                        option.value
                    );

                    changed = true;
                }
            });
        }

        return descendants;
    }

    // =====================================================
    // AUTO GENERATE SLUG
    // =====================================================

    slugInput?.addEventListener('input', () => {
        slugWasEdited = true;
    });


    nameInput?.addEventListener('input', () => {
        if (slugWasEdited) {
            return;
        }

        slugInput.value =
            slugifyVietnamese(nameInput.value);
    });


    // =====================================================
    // RESET CATEGORY EDITOR
    // =====================================================

    function resetCategoryEditor() {
        editingUrl = null;

        categoryForm?.reset();
        if (colorInput) {
            colorInput.value = '#38bdf8';
        }

        if (iconInput) {
            iconInput.value = '';
        }
        resetParentOptions();

        if (statusInput) {
            statusInput.value = 'ACTIVE';
        }

        slugWasEdited = false;


        if (editorTitle) {
            editorTitle.textContent =
                'New Category';
        }


        if (saveButtonText) {
            saveButtonText.textContent =
                'Create Category';
        }


        cancelEditButton?.classList.add(
            'hidden'
        );


        if (errorBox) {
            errorBox.textContent = '';

            errorBox.classList.add(
                'hidden'
            );
        }
    }

    // EDIT CATEGORY
    document
        .querySelectorAll('.edit-category')
        .forEach((button) => {
            button.addEventListener(
                'click',
                () => {
                    editingUrl =
                        button.dataset.updateUrl;

                    const currentCategoryId =
                        button.dataset.id;

                    resetParentOptions();

                    const descendantIds =
                        getDescendantIds(currentCategoryId);

                    Array.from(parentInput.options)
                        .forEach((option) => {
                            if (!option.value) {
                                return;
                            }

                            if (
                                option.value === currentCategoryId ||
                                descendantIds.has(option.value)
                            ) {
                                option.disabled = true;
                            }
                        });

                    nameInput.value =
                        button.dataset.name ?? '';

                    slugInput.value =
                        button.dataset.slug ?? '';

                    parentInput.value =
                        button.dataset.parentId ?? '';

                    descriptionInput.value =
                        button.dataset.description ?? '';
                        
                    colorInput.value =
                        button.dataset.color || '#38bdf8';

                    iconInput.value =
                        button.dataset.icon ?? '';

                    statusInput.value =
                        button.dataset.status ?? 'ACTIVE';


                    slugWasEdited = true;


                    editorTitle.textContent =
                        'Edit Category';

                    saveButtonText.textContent =
                        'Save Changes';


                    cancelEditButton.classList.remove(
                        'hidden'
                    );


                    nameInput.focus();
                }
            );
        });


    // =====================================================
    // CANCEL EDIT
    // =====================================================

    cancelEditButton?.addEventListener(
        'click',
        resetCategoryEditor
    );


    // =====================================================
    // CREATE / UPDATE CATEGORY
    // =====================================================

    categoryForm?.addEventListener(
        'submit',
        async (event) => {
            event.preventDefault();


            errorBox.classList.add(
                'hidden'
            );

            errorBox.textContent = '';


            saveButton.disabled = true;

            saveButton.classList.add(
                'opacity-60'
            );


            try {
                const formData =
                    new FormData(categoryForm);


                /*
                * Laravel Method Spoofing:
                * POST + _method PUT
                */
                if (editingUrl) {
                    formData.append(
                        '_method',
                        'PUT'
                    );
                }


                const response =
                    await fetch(
                        editingUrl ?? categoryStoreUrl,
                        {
                            method: 'POST',

                            headers: {
                                'Accept':
                                    'application/json',

                                'X-Requested-With':
                                    'XMLHttpRequest',
                            },

                            body: formData,
                        }
                    );


                const data =
                    await response.json();


                if (!response.ok) {
                    if (data.errors) {
                        const messages =
                            Object
                                .values(data.errors)
                                .flat();


                        throw new Error(
                            messages.join(' ')
                        );
                    }


                    throw new Error(
                        data.message ??
                        (
                            editingUrl
                                ? 'Không thể cập nhật category.'
                                : 'Không thể tạo category.'
                        )
                    );
                }


                window.location.reload();
            }
            catch (error) {
                errorBox.textContent =
                    error.message;


                errorBox.classList.remove(
                    'hidden'
                );
            }
            finally {
                saveButton.disabled = false;


                saveButton.classList.remove(
                    'opacity-60'
                );
            }
        }
    );

    // =====================================================
    // ACTIVATE / DEACTIVATE CATEGORY
    // =====================================================

    document
        .querySelectorAll('.toggle-category-status')
        .forEach((button) => {
            button.addEventListener(
                'click',
                async () => {
                    const currentStatus =
                        button.dataset.status;

                    const nextStatus =
                        currentStatus === 'ACTIVE'
                            ? 'INACTIVE'
                            : 'ACTIVE';

                    const actionText =
                        nextStatus === 'INACTIVE'
                            ? 'vô hiệu hóa'
                            : 'kích hoạt';

                    const confirmed =
                        await showConfirmModal({
                            title:
                                nextStatus === 'INACTIVE'
                                    ? 'Deactivate Category'
                                    : 'Activate Category',

                            message:
                                `Bạn có chắc muốn ${actionText} category này?`,

                            confirmText:
                                nextStatus === 'INACTIVE'
                                    ? 'Deactivate'
                                    : 'Activate',
                        });

                    if (!confirmed) {
                        return;
                    }

                    const formData =
                        new FormData();

                    formData.append(
                        '_token',
                        csrfToken
                    );

                    formData.append(
                        '_method',
                        'PATCH'
                    );

                    formData.append(
                        'status',
                        nextStatus
                    );

                    try {
                        const response =
                            await fetch(
                                button.dataset.statusUrl,
                                {
                                    method: 'POST',

                                    headers: {
                                        'Accept':
                                            'application/json',

                                        'X-Requested-With':
                                            'XMLHttpRequest',
                                    },

                                    body: formData,
                                }
                            );

                        const data =
                            await response.json();

                        if (!response.ok) {
                            if (data.errors) {
                                throw new Error(
                                    Object
                                        .values(data.errors)
                                        .flat()
                                        .join(' ')
                                );
                            }

                            throw new Error(
                                data.message ??
                                'Không thể cập nhật trạng thái category.'
                            );
                        }

                        window.location.reload();
                    }
                    catch (error) {
                        window.showConfirmModal({
                            title: 'Action failed',
                            message: error.message,
                            confirmText: 'Close',
                            showCancel: false,
                        });
                    }
                }
            );
        });
    // =====================================================
    // TAG MANAGER
    // =====================================================

    const tagManager =
        document.getElementById('tagManager');

    if (tagManager) {
        const tagStoreUrl =
            tagManager.dataset.storeUrl;

        const tagCsrfToken =
            tagManager.dataset.csrfToken;


        // =====================================================
        // TAG ELEMENTS
        // =====================================================

        const tagSearch =
            document.getElementById('tagSearch');

        const tagRows =
            document.querySelectorAll('.tag-row');
            document
                .querySelectorAll('.tag-color')
                .forEach((tag) => {
                    const color =
                        tag.dataset.color || '#8b5cf6';

                    tag.style.color = color;
                    tag.style.borderColor = color;
                });


        const tagForm =
            document.getElementById('tagForm');

        const tagNameInput =
            document.getElementById('tagName');

        const tagSlugInput =
            document.getElementById('tagSlug');

        const tagColorInput =
            document.getElementById('tagColor');

        const tagEditorTitle =
            document.getElementById('tagEditorTitle');

        const saveTagButton =
            document.getElementById('saveTagButton');

        const saveTagButtonText =
            document.getElementById('saveTagButtonText');

        const cancelTagEditButton =
            document.getElementById('cancelTagEdit');


        const tagErrorBox =
            document.getElementById('tagFormError');

        const tagInfoBox =
            document.getElementById('tagFormInfo');


        let editingTagUrl = null;

        let tagSlugWasEdited = false;


        // =====================================================
        // TAG SEARCH
        // =====================================================

        tagSearch?.addEventListener(
            'input',
            () => {
                const keyword =
                    normalizeVietnameseText(tagSearch.value);

                tagRows.forEach((row) => {
                    const name =
                        normalizeVietnameseText(row.dataset.name ?? '');

                    const slug =
                        normalizeVietnameseText(row.dataset.slug ?? '');

                    const matched =
                        name.includes(keyword) ||
                        slug.includes(keyword);

                    row.classList.toggle(
                        'hidden',
                        !matched
                    );
                });
            }
        );


        // =====================================================
        // AUTO TAG SLUG
        // =====================================================
        tagSlugInput?.addEventListener(
            'input',
            () => {
                tagSlugWasEdited = true;
            }
        );
        tagNameInput?.addEventListener(
            'input',
            () => {
                if (tagSlugWasEdited) {
                    return;
                }

                tagSlugInput.value =
                    slugifyVietnamese(tagNameInput.value);
            }
        );

        // =====================================================
        // RESET TAG EDITOR
        // =====================================================

        function resetTagEditor() {
            editingTagUrl = null;

            tagForm?.reset();
            if (tagColorInput) {
                tagColorInput.value = '#8b5cf6';
            }
            tagSlugWasEdited = false;


            if (tagEditorTitle) {
                tagEditorTitle.textContent =
                    'New Tag';
            }


            if (saveTagButtonText) {
                saveTagButtonText.textContent =
                    'Create Tag';
            }


            cancelTagEditButton
                ?.classList.add('hidden');


            if (tagErrorBox) {
                tagErrorBox.textContent = '';
                tagErrorBox.classList.add('hidden');
            }


            if (tagInfoBox) {
                tagInfoBox.textContent = '';
                tagInfoBox.classList.add('hidden');
            }
        }

        // EDIT TAG
        document
            .querySelectorAll('.edit-tag')
            .forEach((button) => {
                button.addEventListener(
                    'click',
                    () => {
                        editingTagUrl =
                            button.dataset.updateUrl;


                        tagNameInput.value =
                            button.dataset.name ?? '';

                        tagSlugInput.value =
                            button.dataset.slug ?? '';

                        tagColorInput.value =
                            button.dataset.color || '#8b5cf6';

                        tagSlugWasEdited = true;


                        tagEditorTitle.textContent =
                            'Edit Tag';

                        saveTagButtonText.textContent =
                            'Save Changes';


                        cancelTagEditButton
                            .classList.remove('hidden');


                        tagErrorBox.classList.add(
                            'hidden'
                        );

                        tagInfoBox.classList.add(
                            'hidden'
                        );


                        tagNameInput.focus();
                    }
                );
            });


        // =====================================================
        // CANCEL TAG EDIT
        // =====================================================

        cancelTagEditButton?.addEventListener(
            'click',
            resetTagEditor
        );


        // =====================================================
        // CREATE / UPDATE TAG
        // =====================================================

        tagForm?.addEventListener(
            'submit',
            async (event) => {
                event.preventDefault();


                tagErrorBox.classList.add(
                    'hidden'
                );

                tagInfoBox.classList.add(
                    'hidden'
                );


                tagErrorBox.textContent = '';
                tagInfoBox.textContent = '';


                saveTagButton.disabled = true;

                saveTagButton.classList.add(
                    'opacity-60'
                );


                try {
                    const formData =
                        new FormData(tagForm);


                    if (editingTagUrl) {
                        formData.append(
                            '_method',
                            'PUT'
                        );
                    }


                    const response =
                        await fetch(
                            editingTagUrl ?? tagStoreUrl,
                            {
                                method: 'POST',

                                headers: {
                                    'Accept':
                                        'application/json',

                                    'X-Requested-With':
                                        'XMLHttpRequest',
                                },

                                body: formData,
                            }
                        );


                    const data =
                        await response.json();


                    if (!response.ok) {
                        if (data.errors) {
                            throw new Error(
                                Object
                                    .values(data.errors)
                                    .flat()
                                    .join(' ')
                            );
                        }


                        throw new Error(
                            data.message ??
                            (
                                editingTagUrl
                                    ? 'Không thể cập nhật tag.'
                                    : 'Không thể tạo tag.'
                            )
                        );
                    }


                    /*
                    * Backend UC05 có trường hợp:
                    * cùng name + slug => reuse Tag cũ.
                    */
                    if (data.reused === true) {
                        tagInfoBox.textContent =
                            data.message ??
                            'Tag đã tồn tại, sử dụng lại tag hiện có.';

                        tagInfoBox.classList.remove(
                            'hidden'
                        );


                        setTimeout(() => {
                            sessionStorage.setItem(
                                'taxonomyActiveTab',
                                'tags'
                            );

                            window.location.reload();
                        }, 700);

                        return;
                    }


                    sessionStorage.setItem(
                        'taxonomyActiveTab',
                        'tags'
                    );


                    window.location.reload();
                }
                catch (error) {
                    tagErrorBox.textContent =
                        error.message;


                    tagErrorBox.classList.remove(
                        'hidden'
                    );
                }
                finally {
                    saveTagButton.disabled = false;


                    saveTagButton.classList.remove(
                        'opacity-60'
                    );
                }
            }
        );


        // =====================================================
        // DELETE TAG
        // =====================================================

        document
            .querySelectorAll('.delete-tag')
            .forEach((button) => {
                button.addEventListener(
                    'click',
                    async () => {
                        const postsCount =
                            Number(
                                button.dataset.postsCount ?? 0
                            );


                        if (postsCount > 0) {
                            await showConfirmModal({
                                title: 'Cannot Delete Tag',
                                message:
                                    'Tag này đang được sử dụng bởi bài viết nên không thể xóa.',
                                confirmText: 'OK',
                            });

                            return;
                        }

                        const tagName =
                            button.dataset.name ?? '';


                        const confirmed =
                            await showConfirmModal({
                                title: 'Delete Tag',

                                message:
                                    `Bạn có chắc muốn xóa tag "${tagName}"? Hành động này không thể hoàn tác.`,

                                confirmText: 'Delete',
                            });

                        if (!confirmed) {
                            return;
                        }

                        const formData =
                            new FormData();


                        formData.append(
                            '_token',
                            tagCsrfToken
                        );


                        formData.append(
                            '_method',
                            'DELETE'
                        );


                        try {
                            const response =
                                await fetch(
                                    button.dataset.deleteUrl,
                                    {
                                        method: 'POST',

                                        headers: {
                                            'Accept':
                                                'application/json',

                                            'X-Requested-With':
                                                'XMLHttpRequest',
                                        },

                                        body: formData,
                                    }
                                );


                            const data =
                                await response.json();


                            if (!response.ok) {
                                throw new Error(
                                    data.message ??
                                    'Không thể xóa tag.'
                                );
                            }


                            sessionStorage.setItem(
                                'taxonomyActiveTab',
                                'tags'
                            );


                            window.location.reload();
                        }
                        catch (error) {
                            window.showConfirmModal({
                                title: 'Action failed',
                                message: error.message,
                                confirmText: 'Close',
                                showCancel: false,
                            });
                        }
                    }
                );
            });
    }
});
