import { shallowRef } from "vue";
import { config } from "@/config";
import { ElMessage } from "element-plus";
import { IToolbarConfig } from "@wangeditor/editor-for-vue";

export const editorRef = shallowRef(null);
export const uploadImageConfig = {
  server: config.baseURL + "/api/v1/upload/image",
  fieldName: "file",
  withCredentials: false,
  //文件大小为10m
  maxFileSize: 10485760,
  beforeUpload: (file) => {
    return true; // 返回false会阻止上传
  },
  customInsert(res: any, insertFn: InsertFnType) {
    insertFn(res.data.url);
  },
  afterUpload: (res) => {
    if (res.code != 0) {
      ElMessage.error(res.message);
    } else {
      ElMessage.success("上传成功");
    }
  },
  allowedFileTypes: ["image/jpeg", "image/png", "image/gif"],
};

export const editorConfig = {
  MENU_CONF: {
    uploadImage: uploadImageConfig,
  },
};

export const toolbarConfig: Partial<IToolbarConfig> = {
  toolbarKeys: [
    "bold",
    "italic",
    "underline",
    "fontSize",
    "color",
    "bgColor",
    "fontFamily",
    "lineHeight",
    "justifyLeft",
    "justifyCenter",
    "justifyRight",
    "insertTable",
    "uploadImage",
  ],
};
export const onEditorCreated = (editor) => {
  editorRef.value = editor;
};
