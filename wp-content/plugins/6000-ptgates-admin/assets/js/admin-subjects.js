/**
 * PTGates Subject Management Admin UI
 * Built with Vue.js 3
 */

(function () {
  "use strict";

  const { createApp, ref, computed, onMounted, reactive } = Vue;

  const app = createApp({
    template: "#ptg-subject-manager-template",
    setup() {
      const loading = ref(true);
      const saving = ref(false);
      const message = ref({ text: "", type: "" }); // type: 'success', 'error'

      // Data
      const courses = ref([]);
      const subjects = ref([]);
      const categories = ref([]); // Distinct categories for dropdowns

      // Modal State
      const showModal = ref(false);
      const modalMode = ref("create"); // 'create', 'edit'
      const currentSubject = reactive({
        config_id: null,
        exam_course: "1교시",
        subject_category: "",
        subject: "",
        subject_code: "",
        question_count: 0,
        sort_order: 0,
        is_active: 1,
      });

      // API URLs
      const apiUrl = ptgAdmin.apiUrl;
      const nonce = ptgAdmin.nonce;

      // Tab State
      const currentTab = ref("manage");
      const rawSubjects = ref([]);

      // Watch tab change
      Vue.watch(currentTab, (newTab) => {
        if (newTab === "mapping") {
          fetchRawSubjects();
        }
      });

      // Fetch Data
      const fetchData = async () => {
        loading.value = true;
        try {
          const response = await fetch(`${apiUrl}subjects`, {
            headers: { "X-WP-Nonce": nonce },
          });
          const result = await response.json();

          if (result.success && result.data) {
            if (result.data.courses) courses.value = result.data.courses;
            if (result.data.subjects) subjects.value = result.data.subjects;
            if (result.data.categories)
              categories.value = result.data.categories;
          } else {
            // Fallback or error handling if needed
            if (result.courses) courses.value = result.courses; // In case structure changes
          }
        } catch (error) {
          showMessage("데이터 로드 실패: " + error.message, "error");
        } finally {
          loading.value = false;
        }
      };

      const fetchRawSubjects = async () => {
        loading.value = true;
        try {
          const response = await fetch(`${apiUrl}raw-subjects`, {
            headers: { "X-WP-Nonce": nonce },
          });
          const result = await response.json();
          if (result.success) {
            rawSubjects.value = result.data.map((item) => ({
              ...item,
              selectedConfigId: "", // For dropdown
            }));
          }
        } catch (error) {
          showMessage("원시 과목 로드 실패: " + error.message, "error");
        } finally {
          loading.value = false;
        }
      };

      const saveMapping = async (rawItem) => {
        if (!rawItem.selectedConfigId) return;

        if (
          !confirm(
            `'${rawItem.subject}'을(를) 선택한 정식 과목으로 매핑하시겠습니까?\n이 작업은 되돌릴 수 없습니다.`
          )
        )
          return;

        saving.value = true;
        try {
          const response = await fetch(`${apiUrl}subject/map`, {
            method: "POST",
            headers: {
              "Content-Type": "application/json",
              "X-WP-Nonce": nonce,
            },
            body: JSON.stringify({
              old_subject: rawItem.subject,
              new_subject_id: rawItem.selectedConfigId,
            }),
          });
          const result = await response.json();
          if (result.success) {
            showMessage(result.data.message, "success");
            fetchRawSubjects(); // Refresh list
          } else {
            showMessage(result.message || "매핑 실패", "error");
          }
        } catch (error) {
          showMessage("매핑 오류: " + error.message, "error");
        } finally {
          saving.value = false;
        }
      };

      const officialSubjectsList = computed(() => subjects.value);

      // Computed: Subjects grouped by Course and Category
      // Computed: Subjects grouped by Course and Category
      const subjectsByCourseAndCategory = computed(() => {
        const grouped = {};
        courses.value.forEach((course) => {
          const courseSubjects = subjects.value.filter(
            (s) =>
              s.exam_course === course.exam_course &&
              parseInt(s.is_active) === 1
          );

          // Group by category
          const byCategory = {};
          courseSubjects.forEach((subj) => {
            const cat = subj.subject_category || "기타";
            if (!byCategory[cat]) byCategory[cat] = [];
            byCategory[cat].push(subj);
          });

          // Convert to array and calculate totals
          const categoryList = Object.keys(byCategory).map((catName) => {
            const subs = byCategory[catName];
            // Sort subjects by sort_order
            subs.sort(
              (a, b) => parseInt(a.sort_order) - parseInt(b.sort_order)
            );

            const total = subs.reduce(
              (sum, s) => sum + parseInt(s.question_count),
              0
            );
            // Find min sort_order for category sorting
            const minOrder =
              subs.length > 0
                ? Math.min(...subs.map((s) => parseInt(s.sort_order)))
                : 9999;

            return {
              name: catName,
              subjects: subs,
              total: total,
              minOrder: minOrder,
            };
          });

          // Sort categories by their first subject's sort_order
          categoryList.sort((a, b) => a.minOrder - b.minOrder);

          grouped[course.exam_course] = categoryList;
        });
        return grouped;
      });

      // Computed: Total questions per course
      const totalQuestionsByCourse = computed(() => {
        const totals = {};
        courses.value.forEach((course) => {
          // Flatten subjects for this course to calculate total
          const courseSubjects = subjects.value.filter(
            (s) =>
              s.exam_course === course.exam_course &&
              parseInt(s.is_active) === 1
          );
          totals[course.exam_course] = courseSubjects.reduce(
            (sum, s) => sum + parseInt(s.question_count),
            0
          );
        });
        return totals;
      });

      // Actions
      const showMessage = (text, type = "success") => {
        message.value = { text, type };
        setTimeout(() => (message.value.text = ""), 3000);
      };

      const updateCourseTotal = async (course) => {
        saving.value = true;
        try {
          const response = await fetch(`${apiUrl}exam-course/update`, {
            method: "POST",
            headers: {
              "Content-Type": "application/json",
              "X-WP-Nonce": nonce,
            },
            body: JSON.stringify({
              id: course.id,
              total_questions: course.total_questions,
            }),
          });
          const result = await response.json();
          if (response.ok) {
            showMessage("총 문항 수가 저장되었습니다.");
          } else {
            throw new Error(result.message || "저장 실패");
          }
        } catch (error) {
          showMessage(error.message, "error");
        } finally {
          saving.value = false;
        }
      };

      const openModal = (mode, subject = null, courseName = "1교시") => {
        modalMode.value = mode;
        if (mode === "edit" && subject) {
          Object.assign(currentSubject, subject);
        } else {
          // Reset for create
          currentSubject.config_id = null;
          currentSubject.exam_course = courseName;
          currentSubject.subject_category = "";
          currentSubject.subject = "";
          currentSubject.subject_code = "";
          currentSubject.question_count = 0;
          currentSubject.sort_order = 0;
          currentSubject.is_active = 1;
        }
        showModal.value = true;
      };

      const closeModal = () => {
        showModal.value = false;
      };

      const saveSubject = async () => {
        saving.value = true;
        try {
          const endpoint =
            modalMode.value === "create" ? "subject/create" : "subject/update";
          const response = await fetch(`${apiUrl}${endpoint}`, {
            method: "POST",
            headers: {
              "Content-Type": "application/json",
              "X-WP-Nonce": nonce,
            },
            body: JSON.stringify(currentSubject),
          });
          const result = await response.json();

          if (response.ok) {
            showMessage(
              modalMode.value === "create"
                ? "과목이 추가되었습니다."
                : "과목이 수정되었습니다."
            );
            closeModal();
            fetchData(); // Reload data
          } else {
            throw new Error(result.message || "저장 실패");
          }
        } catch (error) {
          showMessage(error.message, "error");
        } finally {
          saving.value = false;
        }
      };

      const deleteSubject = async (id) => {
        if (!confirm("정말 삭제하시겠습니까?")) return;

        saving.value = true;
        try {
          const response = await fetch(`${apiUrl}subject/delete`, {
            method: "POST",
            headers: {
              "Content-Type": "application/json",
              "X-WP-Nonce": nonce,
            },
            body: JSON.stringify({ config_id: id }),
          });
          const result = await response.json();

          if (response.ok) {
            showMessage("과목이 삭제되었습니다.");
            fetchData();
          } else {
            throw new Error(result.message || "삭제 실패");
          }
        } catch (error) {
          showMessage(error.message, "error");
        } finally {
          saving.value = false;
        }
      };

      // Drag and Drop Handler (Simple implementation using array move)
      // For full DnD, we might need a library, but here we can implement simple up/down or use HTML5 DnD
      // Let's implement simple Up/Down for now to avoid dependency complexity,
      // or use native DnD if requested. User asked for "drag-and-drop".
      // We will use native HTML5 DnD.

      const dragStart = (event, index, courseName) => {
        event.dataTransfer.effectAllowed = "move";
        event.dataTransfer.setData(
          "text/plain",
          JSON.stringify({ index, courseName })
        );
      };

      const drop = async (event, targetIndex, targetCourseName) => {
        const data = JSON.parse(event.dataTransfer.getData("text/plain"));
        if (data.courseName !== targetCourseName) return; // Only allow reorder within same course

        const list = subjectsByCourse.value[targetCourseName];
        const item = list[data.index];

        // Remove from old position
        const newList = [...list];
        newList.splice(data.index, 1);
        // Insert at new position
        newList.splice(targetIndex, 0, item);

        // Update sort_order for all items in this course
        // We need to update locally first then save to server
        // But since we don't have a batch update API yet, we might need to call update for changed items
        // Or just update local state and have a "Save Order" button?
        // User expects "drag-and-drop support", usually implies immediate or explicit save.
        // Let's implement immediate save for simplicity of UX, but batching might be better.
        // For now, let's just update the sort_order locally and call update for each.

        saving.value = true;
        try {
          const updates = newList.map((subj, idx) => {
            return fetch(`${apiUrl}subject/update`, {
              method: "POST",
              headers: {
                "Content-Type": "application/json",
                "X-WP-Nonce": nonce,
              },
              body: JSON.stringify({
                config_id: subj.config_id,
                sort_order: idx + 1, // 1-based
              }),
            });
          });

          await Promise.all(updates);
          showMessage("순서가 변경되었습니다.");
          fetchData();
        } catch (error) {
          showMessage("순서 저장 실패: " + error.message, "error");
        } finally {
          saving.value = false;
        }
      };

      const initializeDefaults = async () => {
        if (!confirm("기본 데이터로 초기화하시겠습니까?")) return;

        loading.value = true;
        try {
          const response = await fetch(`${apiUrl}seed-defaults`, {
            method: "POST",
            headers: { "X-WP-Nonce": nonce },
          });
          const result = await response.json();

          if (response.ok && result.success) {
            showMessage(
              result.message || result.data?.message || "초기화 완료"
            );
            fetchData();
          } else {
            throw new Error(result.message || "초기화 실패");
          }
        } catch (error) {
          showMessage(error.message, "error");
        } finally {
          loading.value = false;
        }
      };

      onMounted(() => {
        fetchData();
      });

      return {
        loading,
        saving,
        message,
        courses,
        subjectsByCourseAndCategory,
        totalQuestionsByCourse,
        updateCourseTotal,
        showModal,
        modalMode,
        currentSubject,
        openModal,
        closeModal,
        saveSubject,
        deleteSubject,
        dragStart,
        drop,
        categories,
        initializeDefaults,
        currentTab,
        rawSubjects,
        officialSubjectsList,
        saveMapping,
      };
    },
  });

  app.mount("#ptg-subject-manager-app");
})();
