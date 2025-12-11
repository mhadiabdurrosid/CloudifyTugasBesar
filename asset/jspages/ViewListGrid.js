const btnList = document.getElementById("btnList");
const btnGrid = document.getElementById("btnGrid");
const wrapper = document.getElementById("fileWrapper");
const listHeader = document.getElementById("listHeader");

btnList.onclick = () => {
  wrapper.classList.remove("grid-view");
  wrapper.classList.add("list-view");

  listHeader.classList.remove("hidden");

  document
    .querySelectorAll(".grid-item")
    .forEach((el) => el.classList.add("hidden"));
  document
    .querySelectorAll(".list-item")
    .forEach((el) => el.classList.remove("hidden"));
};

btnGrid.onclick = () => {
  wrapper.classList.add("grid-view");
  wrapper.classList.remove("list-view");

  listHeader.classList.add("hidden");

  document
    .querySelectorAll(".grid-item")
    .forEach((el) => el.classList.remove("hidden"));
  document
    .querySelectorAll(".list-item")
    .forEach((el) => el.classList.add("hidden"));
};
