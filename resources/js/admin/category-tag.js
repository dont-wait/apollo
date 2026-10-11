document.addEventListener('DOMContentLoaded', () => {
    /*
     * Script này được load chung qua app.js.
     * Nếu không phải trang Categories & Tags thì dừng luôn.
     */
    const taxonomyManager = document.getElementById('taxonomyManager');

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

    const openCreateCategoryButton =
        document.getElementById('openCreateCategory');

    const openCreateTagButton =
        document.getElementById('openCreateTag');


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

        openCreateCategoryButton.hidden = false;
        openCreateTagButton.hidden = true;

        setActiveTab(
            categoryTabButton,
            tagTabButton
        );
    }


    function showTagTab() {
        tagsTab.classList.remove('hidden');
        categoriesTab.classList.add('hidden');

        openCreateCategoryButton.hidden = true;
        openCreateTagButton.hidden = false;

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
            searchInput?.value.toLowerCase().trim() ?? '';

        const status =
            statusFilter?.value ?? 'ALL';


        categoryRows.forEach((row) => {
            const name =
                row.dataset.name ?? '';

            const slug =
                row.dataset.slug ?? '';

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


    let editingUrl = null;

    let slugWasEdited = false;


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


        slugInput.value = nameInput.value
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase()
            .trim()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');
    });


    // =====================================================
    // RESET CATEGORY EDITOR
    // =====================================================

    function resetCategoryEditor() {
        editingUrl = null;

        categoryForm?.reset();

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


    // =====================================================
    // OPEN NEW CATEGORY
    // =====================================================

    openCreateCategoryButton?.addEventListener(
        'click',
        () => {
            showCategoryTab();

            resetCategoryEditor();

            nameInput?.focus();
        }
    );


    // =====================================================
    // EDIT CATEGORY
    // =====================================================

    document
        .querySelectorAll('.edit-category')
        .forEach((button) => {
            button.addEventListener(
                'click',
                () => {
                    editingUrl =
                        button.dataset.updateUrl;


                    nameInput.value =
                        button.dataset.name ?? '';

                    slugInput.value =
                        button.dataset.slug ?? '';

                    parentInput.value =
                        button.dataset.parentId ?? '';

                    descriptionInput.value =
                        button.dataset.description ?? '';

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
                        confirm(
                            `Bạn có chắc muốn ${actionText} category này?`
                        );


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
                        'PUT'
                    );


                    formData.append(
                        'name',
                        button.dataset.name ?? ''
                    );


                    formData.append(
                        'slug',
                        button.dataset.slug ?? ''
                    );


                    formData.append(
                        'description',
                        button.dataset.description ?? ''
                    );


                    formData.append(
                        'status',
                        nextStatus
                    );


                    if (button.dataset.parentId) {
                        formData.append(
                            'parent_id',
                            button.dataset.parentId
                        );
                    }


                    try {
                        const response =
                            await fetch(
                                button.dataset.updateUrl,
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
                        alert(
                            error.message
                        );
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


        const tagForm =
            document.getElementById('tagForm');

        const tagNameInput =
            document.getElementById('tagName');

        const tagSlugInput =
            document.getElementById('tagSlug');


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
                    tagSearch.value
                        .toLowerCase()
                        .trim();


                tagRows.forEach((row) => {
                    const name =
                        row.dataset.name ?? '';

                    const slug =
                        row.dataset.slug ?? '';


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
                    tagNameInput.value
                        .normalize('NFD')
                        .replace(/[\u0300-\u036f]/g, '')
                        .toLowerCase()
                        .trim()
                        .replace(/[^a-z0-9]+/g, '-')
                        .replace(/^-+|-+$/g, '');
            }
        );


        // =====================================================
        // RESET TAG EDITOR
        // =====================================================

        function resetTagEditor() {
            editingTagUrl = null;

            tagForm?.reset();

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


        // =====================================================
        // NEW TAG BUTTON
        // =====================================================

        openCreateTagButton?.addEventListener(
            'click',
            () => {
                showTagTab();

                resetTagEditor();

                tagNameInput?.focus();
            }
        );


        // =====================================================
        // EDIT TAG
        // =====================================================

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


                        /*
                        * UI chặn trước.
                        * Backend vẫn kiểm tra lại và trả 409.
                        */
                        if (postsCount > 0) {
                            alert(
                                'Không thể xóa tag vì tag đang được sử dụng bởi bài viết.'
                            );

                            return;
                        }


                        const tagName =
                            button.dataset.name ?? '';


                        const confirmed =
                            confirm(
                                `Bạn có chắc muốn xóa tag "${tagName}"?`
                            );


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
                            alert(
                                error.message
                            );
                        }
                    }
                );
            });
    }
});