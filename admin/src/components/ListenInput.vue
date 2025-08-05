 <script>
  import { emit } from '@/eventBus';
  export default {
    data() {
      return {
        scanData: '', // 用于存储扫码枪输入的数据
        hasListened: false
      };
    },
    mounted() { 
      document.addEventListener('keypress', this.handleKeyPress);
    },
    methods: {
      handleKeyPress(e) {   
        if (e.target.tagName !== 'INPUT' && e.target.tagName !== 'TEXTAREA' && !e.ctrlKey && !e.altKey && !e.shiftKey && !e.metaKey) {   
          const char = e.key;  
          if (char && char.length === 1) {
            this.scanData += char;
          }
          if (e.which === 13) {  
            this.processScanData(this.scanData);
            this.scanData = '';
          }
        }
      },
      processScanData(data) {   
        console.log('扫描数据:', data);
        emit('scan-input', data);   
        emit('scan-input-next', data);   
         //判断URL中是否包含 /orders/create
        if (window.location.href.includes("/orders/create")) {
          //调用子页面的handleParseUserCode方法 
          return;
        }   
      }
    }
  };
  </script>
  <template> 
  </template>